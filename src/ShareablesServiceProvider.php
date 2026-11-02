<?php

namespace Whilesmart\Shareables;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Whilesmart\Shareables\Models\Share;
use Whilesmart\Shareables\Models\Audience;

class ShareablesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/shareables.php', 'shareables');
    }

    public function boot(): void
    {
        foreach (['share' => Share::class, 'audience' => Audience::class] as $key => $baseClass) {
            $model = config('shareables.models.' . $key, $baseClass);
            if (! is_string($model) || ! is_a($model, $baseClass, true)) {
                throw new InvalidArgumentException('The configured shareables ' . $key . ' model must extend ' . $baseClass . '.');
            }
        }
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/shareables.php' => config_path('shareables.php'),
        ], 'shareables-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'shareables-migrations');
    }
}
