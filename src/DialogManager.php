<?php

declare(strict_types=1);

namespace KootLabs\TelegramBotDialogs;

use KootLabs\TelegramBotDialogs\Exceptions\ControlFlow\SwitchToAnotherDialog;
use KootLabs\TelegramBotDialogs\Exceptions\ControlFlow\SwitchToAnotherStep;
use KootLabs\TelegramBotDialogs\Objects\BotInitiatedUpdate;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;

/** @api */
final class DialogManager
{
    /** Bot instance to use it for all API calls. */
    private Api $bot;

    /** Storage to store Dialog state between requests. */
    private DialogRepository $repository;

    public function __construct(Api $bot, DialogRepository $repository)
    {
        $this->bot = $bot;
        $this->repository = $repository;
    }

    /** @api Use non-default Bot for API calls */
    public function setBot(Api $bot): self
    {
        $this->bot = $bot;
        return $this;
    }

    /**
     * @api Activate a new Dialog.
     * to start it - call {@see \KootLabs\TelegramBotDialogs\DialogManager::processUpdate}
     */
    public function activate(Dialog $dialog): void
    {
        $this->persistDialog($dialog);
    }

    /** @api Remove the current active Dialog from a Storage. */
    public function forgetActiveDialog(Update $update): void
    {
        $dialog = $this->resolveActiveDialog($update);
        if ($dialog instanceof Dialog) {
            $this->forgetDialog($dialog);
        }
    }

    /**
     * Initiate a new Dialog from the server side (e.g., by cron).
     * Note, a User firstly should start a chat with a bot (bot can't initiate a chat — this is TG Bot API limitation).
     * @api
     * @param array<mixed> $updateData Raw Update data to create {@see \Telegram\Bot\Objects\Update} instance from.
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException In case a chat is not available (bots can't initiate chats) or other API errors.
     */
    public function initiateDialog(Dialog $dialog, array $updateData = []): void
    {
        // Required in processUpdate to get instance of the current active Dialog from a Storage
        if (empty($updateData)) {
            $updateData = [
                'message' => [
                    'chat' => [
                        'id' => $dialog->getChatId(),
                    ],
                ],
            ];

            $userId = $dialog->getUserId();
            if ($userId !== null){
                $updateData['message']['from'] = ['id' => $userId];
            }
        }

        $update = new BotInitiatedUpdate($updateData);
        $this->forgetActiveDialog($update);
        $this->activate($dialog);
        $this->processUpdate($update);
    }

    /**
     * Run the next step of the active Dialog.
     * This is a thin wrapper for {@see \KootLabs\TelegramBotDialogs\Dialog::performStep}
     * to store and restore Dialog state between request-response calls.
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     * @api
     */
    public function processUpdate(Update $update): void
    {
        $dialog = $this->resolveActiveDialog($update);
        if (! $dialog instanceof Dialog) {
            return;
        }

        do {
            $performOneMoreStepRequired = false;
            try {
                $dialog->performStep($update);
            } catch (SwitchToAnotherStep) {
                $performOneMoreStepRequired = true;
            } catch (SwitchToAnotherDialog $exception) {
                $this->forgetDialog($dialog);
                $this->activate($exception->nextDialog);
                $this->processUpdate($update);
                return;
            }
        } while($performOneMoreStepRequired);

        $dialog->isCompleted()
            ? $this->forgetDialog($dialog)
            : $this->persistDialog($dialog);
    }

    /** @return non-empty-string|null */
    private function resolveDialogKey(Update $update): ?string
    {
        $chatId = $update->getChat()->get('id');
        if (! is_int($chatId)) {
            return null; // Chat id is not available in the Update
        }

        // As for 1-1 personal chat and multi-user chat, where bot should treat all users messages as one dialog
        $chatBoundedDialogKey = $this->generateDialogKey($chatId);
        if ($this->repository->has($chatBoundedDialogKey)) {
            return $chatBoundedDialogKey;
        }

        $userId = $update->getMessage()->get('from')?->id;
        if (! is_int($userId)) {
            return null;
        }

        // As for multi-user chat, where bot should treat all messages of every user as a separate dialog
        $userBoundedDialogKey = $this->generateDialogKey($chatId, $userId);
        if ($this->repository->has($userBoundedDialogKey)) {
            return $userBoundedDialogKey;
        }

        return null;
    }

    /** @api Whether an active Dialog exist for a given Update. */
    public function exists(Update $update): bool
    {
        return is_string($this->resolveDialogKey($update));
    }

    /**
     * @api Whether an active Dialog exist for a given Update.
     * Alias for the {@see \KootLabs\TelegramBotDialogs\DialogManager::exists}
     */
    public function hasActiveDialog(Update $update): bool
    {
        return $this->exists($update);
    }

    /** Get instance of the current active Dialog from a Storage. */
    private function resolveActiveDialog(Update $update): ?Dialog
    {
        $storeDialogKey = $this->resolveDialogKey($update);
        if ($storeDialogKey === null) {
            return null;
        }

        $dialog = $this->retrieveDialog($storeDialogKey);
        $dialog->setBot($this->bot);

        return $dialog;
    }

    /** Forget Dialog state. */
    private function forgetDialog(Dialog $dialog): void
    {
        $this->repository->forget($this->getDialogKey($dialog));
    }

    /** Store all Dialog. */
    private function persistDialog(Dialog $dialog): void
    {
        $this->repository->put($this->getDialogKey($dialog), $dialog, $dialog->getTtl());
    }

    /** Retrieve Dialog. */
    private function retrieveDialog(string $key): Dialog
    {
        return $this->repository->get($key);
    }

    /** @return non-empty-string */
    private function getDialogKey(Dialog $dialog): string
    {
        return $this->generateDialogKey(
            $dialog->getChatId(),
            $dialog->getUserId(),
        );
    }

    /** @return non-empty-string */
    private function generateDialogKey(int $chatId, ?int $userId = null): string
    {
        return implode('-', array_filter([
            $chatId,
            $userId,
        ]));
    }
}
