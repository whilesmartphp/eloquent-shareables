<?php

namespace Whilesmart\Shareables;

use Illuminate\Support\ServiceProvider;

class ShareablesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sharables.php', 'shareables');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/sharables.php' => config_path('sharables.php'),
        ], 'shareables-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'shareables-migrations');
    }
}
