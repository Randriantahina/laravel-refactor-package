<?php

namespace Shan\LaravelRefactor;

use Illuminate\Support\ServiceProvider;
use Shan\LaravelRefactor\Commands\MoveCommand;
use Shan\LaravelRefactor\Commands\RenameCommand;

class LaravelRefactorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-refactor.php',
            'laravel-refactor'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RenameCommand::class,
                MoveCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/laravel-refactor.php' => config_path('laravel-refactor.php'),
            ], 'laravel-refactor-config');
        }
    }
}
