<?php

namespace Shan\LaravelRefactor;

use Illuminate\Support\ServiceProvider;
use Shan\LaravelRefactor\Commands\MoveCommand;
use Shan\LaravelRefactor\Commands\RenameCommand;
use Shan\LaravelRefactor\Scanners\BladeFileScanner;
use Shan\LaravelRefactor\Scanners\PhpFileScanner;
use Shan\LaravelRefactor\Services\ConflictDetector;
use Shan\LaravelRefactor\Services\NamespaceResolver;
use Shan\LaravelRefactor\Services\RefactorService;
use Shan\LaravelRefactor\Services\RollbackService;
use Shan\LaravelRefactor\Updaters\BladeFileUpdater;
use Shan\LaravelRefactor\Updaters\PhpFileUpdater;

class LaravelRefactorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-refactor.php',
            'laravel-refactor'
        );

        $this->app->singleton(NamespaceResolver::class, fn () => new NamespaceResolver(base_path()));
        $this->app->singleton(ConflictDetector::class);
        $this->app->singleton(RollbackService::class, fn () => new RollbackService(base_path()));
        $this->app->singleton(PhpFileScanner::class);
        $this->app->singleton(BladeFileScanner::class);
        $this->app->singleton(PhpFileUpdater::class);
        $this->app->singleton(BladeFileUpdater::class);

        $this->app->singleton(RefactorService::class, fn ($app) => new RefactorService(
            namespaceResolver: $app->make(NamespaceResolver::class),
            conflictDetector: $app->make(ConflictDetector::class),
            rollbackService: $app->make(RollbackService::class),
            phpScanner: $app->make(PhpFileScanner::class),
            bladeScanner: $app->make(BladeFileScanner::class),
            phpUpdater: $app->make(PhpFileUpdater::class),
            bladeUpdater: $app->make(BladeFileUpdater::class),
            basePath: base_path(),
        ));
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
