<?php

declare(strict_types=1);

namespace KootLabs\TelegramBotDialogs\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use KootLabs\TelegramBotDialogs\DialogManager;
use KootLabs\TelegramBotDialogs\DialogRepository;

/** @api */
final class DialogsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /** @inheritDoc */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/telegramdialogs.php', 'telegramdialogs');

        $this->offerPublishing();
        $this->registerBindings();
    }

    /** Setup the resource publishing groups. */
    private function offerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/telegramdialogs.php' => config_path('telegramdialogs.php'),
            ], 'telegram-config');
        }
    }

    private function registerBindings(): void
    {
        $this->app->bind(DialogRepository::class, function (Container $app): DialogRepository {
            $config = $app->make('config');
            $store = $app->make('cache')->store($config->get('telegramdialogs.cache_store'));
            assert($store instanceof \Illuminate\Contracts\Cache\Repository);

            return new DialogRepository($store, $config->get('telegramdialogs.cache_prefix'));
        });

        $this->app->alias(DialogManager::class, 'telegram.dialogs');
    }

    /**
     * @inheritDoc
     * @return list<string>
     */
    public function provides(): array
    {
        return [
            'telegram.dialogs',
            DialogManager::class,
            DialogRepository::class,
        ];
    }
}
