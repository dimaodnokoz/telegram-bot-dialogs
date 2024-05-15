<?php

declare(strict_types=1);

namespace KootLabs\TelegramBotDialogs\Dialogs;

use KootLabs\TelegramBotDialogs\Dialog;
use Telegram\Bot\Objects\Update;

/**
 * An example of Dialog class for demo purposes.
 * @internal
 * @api
 */
final class HelloExampleDialog extends Dialog
{
    /** @var list<string> List of method to execute. The order defines the sequence */
    protected array $steps = ['sayHello', 'empathyReply', 'sayBye'];

    public function sayHello(Update $update): void
    {
        //Used for test purposes
        if(!isset($this->bot)){return;}

        $this->bot->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => '👋! How are you?',
        ]);
    }

    public function empathyReply(Update $update): void
    {
        //Used for test purposes
        if(!isset($this->bot)){return;}

        $this->bot->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "I’m also {$update->message?->text}!",
            'reply_to_message_id' => $update->message?->messageId,
        ]);
    }

    public function sayBye(Update $update): void
    {
        //Used for test purposes
        if(!isset($this->bot)){return;}

        if ($update->message?->text === 'again') {
            $this->bot->sendMessage([
                'chat_id' => $this->getChatId(),
                'text' => 'OK, send me something — we will start again! 😀',
                'reply_to_message_id' => $update->message?->messageId,
            ]);

            $this->jump('sayHello');

            return;
        }

        $this->bot->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => 'Bye!',
            'reply_to_message_id' => $update->message->messageId,
        ]);
    }

    public bool $afterLastStepCalled = false;

    protected function afterLastStep(Update $update): void
    {
        $this->afterLastStepCalled = true;
    }
}
