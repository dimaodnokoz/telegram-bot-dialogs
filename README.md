[![CI](https://github.com/koot-labs/telegram-bot-dialogs/actions/workflows/ci.yml/badge.svg)](https://github.com/koot-labs/telegram-bot-dialogs/actions/workflows/ci.yml)
[![Backward compatibility check](https://github.com/koot-labs/telegram-bot-dialogs/actions/workflows/backward-compatibility-check.yml/badge.svg)](https://github.com/koot-labs/telegram-bot-dialogs/actions/workflows/backward-compatibility-check.yml)
[![Type coverage](https://shepherd.dev/github/koot-labs/telegram-bot-dialogs/coverage.svg)](https://shepherd.dev/github/koot-labs/telegram-bot-dialogs)
[![Psalm level](https://shepherd.dev/github/koot-labs/telegram-bot-dialogs/level.svg)](https://shepherd.dev/github/koot-labs/telegram-bot-dialogs)

<p align="center"><img src="https://user-images.githubusercontent.com/5278175/176997422-79e5c4c1-ff43-438e-b30e-651bb8e17bcf.png" alt="Dialogs" width="400"></p>

# Dialogs plugin for Telegram Bot API PHP SDK

The extension for [Telegram Bot API PHP SDK](https://github.com/irazasyed/telegram-bot-sdk) v3.1+ that allows to implement dialogs for telegram bots.


## About this fork

Original package is not maintained anymore and does not support Telegram Bot API PHP SDK v3.
The goal of the fork is to maintain the package compatible with the latest [Telegram Bot API PHP SDK](https://github.com/irazasyed/telegram-bot-sdk),
PHP 8+ and Laravel features, focus on stability, better DX and readability.


## Installation

You can easily install the package using Composer:

```shell
composer require koot-labs/telegram-bot-dialogs
```
Package requires PHP >= 8.0


## Usage

1. Create a Dialog class
2. [Create a Telegram command](https://telegram-bot-sdk.readme.io/docs/commands-system) and start a Dialog from `Command::handle()`.
3. Setup your controller class to proceed active Dialog on income webhook request.


### 1. Create a Dialog class

Each dialog should be implemented as class that extends basic `Dialog` as you can see in [HelloExampleDialog](https://github.com/koot-labs/telegram-bot-dialogs/blob/master/src/Dialogs/HelloExampleDialog.php) or the code bellow:

```php
use KootLabs\TelegramBotDialogs\Dialog;
use Telegram\Bot\Objects\Update;

final class HelloDialog extends Dialog
{
    /** @var list<string> List of method to execute. The order defines the sequence */
    protected array $steps = ['sayHello', 'sayOk', 'sayBye'];

    public function sayHello(Update $update): void
    {
        $this->bot->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => 'Hello! How are you?',
        ]);
    }

    public function sayOk(Update $update): void
    {
        $this->bot->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => "I'm also OK :)",
        ]);
    }

    public function sayBye(Update $update): void
    {
        $this->bot->sendMessage([
            'chat_id' => $this->getChatId(),
            'text' => 'Bye!',
        ]);
        $this->jump('sayHello');
    }
}
```


### 2. Create a Telegram command

To initiate a dialog please use `DialogManager` (or, if you use Laravel, `Dialogs` Facade) — it will care about storing and recovering `Dialog` instance state between steps/requests.
To execute the first and next steps please call `Dialogs::procceed()` method with update object as an argument.
Also, it is possible to use dialogs with Telegram commands and DI through type hinting.

```php
use App\Dialogs\HelloExampleDialog;
use KootLabs\TelegramBotDialogs\Laravel\Facades\Dialogs;
use Telegram\Bot\Commands\Command;

final class HelloCommand extends Command
{
    /** @var string Command name */
    protected $name = 'hello';

    /** @var string Command description */
    protected $description = 'Just say "Hello" and ask few questions in a dialog mode.';

    public function handle(): void
    {
        Dialogs::activate(new HelloExampleDialog($this->update->getChat()->id));
    }
}
```


### 3. Setup your controller

Process request inside your Laravel webhook controller:

```php
use Telegram\Bot\BotsManager;
use KootLabs\TelegramBotDialogs\DialogManager;

final class TelegramWebhookController
{
    public function handle(DialogManager $dialogs, BotsManager $botsManager): void
    {
        $update = $bot->commandsHandler(true);

        $dialogs->exists($update)
            ? $dialogs->proceed($update) // proceed an active dialog (activated in HelloCommand)
            : $botsManager->sendMessage([ // fallback message
                'chat_id' => $update->getChat()->id,
                'text' => 'There is no active dialog at this moment. Type /hello to start a new dialog.',
            ]);
    }
}
```

### `Dialog` class API

- `isEnd()` - Check the end of the dialog
- 🔐 `end()` - End dialog
- 🔐 `jump(string $stepName)` - Jump to the particular step, where `$step` is the `public` method name
- 🔐 `memory` - Laravel Collection to store intermediate data between steps


### `DialogManager` class API

`DialogManager` is in charge of:
 - storing and recovering Dialog instances between steps/requests
 - running Dialog steps (using Dialog public API)
 - switching/activating Dialogs

For Laravel apps, the package provides `Dialogs` Facade, that proxies calls to `DialogManager` class.

`DialogManager` public API:
- `activate(\KootLabs\TelegramBotDialogs\Dialog $dialog)` - Activate a new Dialog (without running it). The same user/chat may have few open Dialogs, DialogManager should know which one is active.
- `proceed(\Telegram\Bot\Objects\Update $update)` - Run the next step handler for the active Dialog (if exists)
- `exists(\Telegram\Bot\Objects\Update $update)` - Check for existing Dialog for a given Update (based on chat_id and optional user_id)
- `setBot(\Telegram\Bot\Api $bot)` - Use non-default Bot for Telegram Bot API calls


## ToDo

Tasks to do for v1.0:

- [x] Add documentation and examples
- [x] Support for channel bots
- [ ] Improve test coverage
- [ ] Improve developer experience (cleaner API (similar method in Dialog and DialogManager))
- [ ] Reach message type validation
- [ ] Reach API to validate message types and content
- [ ] Support `\Iterator`s and/or `\Generator`s for Dialog steps


## Backward compatibility promise

Dialogs package uses [Semver 2.0](https://semver.org/). This means that versions are tagged with MAJOR.MINOR.PATCH.
Only a new major version will be allowed to break backward compatibility (BC).

Classes marked as `@experimental` or `@internal` are not included in our backward compatibility promise.
You are also not guaranteed that the value returned from a method is always the same.
You are guaranteed that the data type will not change.

PHP 8 introduced [named arguments](https://wiki.php.net/rfc/named_params), which increased the cost and reduces flexibility for package maintainers.
The names of the arguments for methods in Dialogs is not included in our BC promise.
