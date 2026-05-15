<?php

namespace Shan\LaravelRefactor\Tests\Feature\Support;

use Shan\LaravelRefactor\Scanners\BladeFileScanner;
use Shan\LaravelRefactor\Scanners\PhpFileScanner;
use Shan\LaravelRefactor\Services\ConflictDetector;
use Shan\LaravelRefactor\Services\NamespaceResolver;
use Shan\LaravelRefactor\Services\RefactorService;
use Shan\LaravelRefactor\Services\RollbackService;
use Shan\LaravelRefactor\Updaters\BladeFileUpdater;
use Shan\LaravelRefactor\Updaters\PhpFileUpdater;

/**
 * @property \Illuminate\Foundation\Application $app
 */
trait FakeApp
{
    protected string $basePath;

    protected function setUpFakeApp(): void
    {
        $this->basePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'refactor-test-' . uniqid();

        mkdir($this->basePath . '/app/Models', 0755, true);
        mkdir($this->basePath . '/resources/views', 0755, true);

        file_put_contents(
            $this->basePath . '/composer.json',
            json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]])
        );

        config()->set('laravel-refactor.scan_paths', ['app', 'resources/views']);
        config()->set('laravel-refactor.backup_path', 'storage/app/refactor-backups');
        config()->set('laravel-refactor.excluded_paths', []);

        $this->rebindServices();
    }

    protected function tearDownFakeApp(): void
    {
        if (isset($this->basePath) && is_dir($this->basePath)) {
            $this->deleteDirectory($this->basePath);
        }
    }

    protected function rebindServices(): void
    {
        $resolver = new NamespaceResolver($this->basePath);
        $rollback = new RollbackService($this->basePath);
        $detector = new ConflictDetector($resolver);

        $this->app->instance(NamespaceResolver::class, $resolver);
        $this->app->instance(RollbackService::class, $rollback);
        $this->app->instance(ConflictDetector::class, $detector);

        $this->app->singleton(RefactorService::class, fn ($app) => new RefactorService(
            namespaceResolver: $app->make(NamespaceResolver::class),
            conflictDetector:  $app->make(ConflictDetector::class),
            rollbackService:   $app->make(RollbackService::class),
            phpScanner:        $app->make(PhpFileScanner::class),
            bladeScanner:      $app->make(BladeFileScanner::class),
            phpUpdater:        $app->make(PhpFileUpdater::class),
            bladeUpdater:      $app->make(BladeFileUpdater::class),
            basePath:          $this->basePath,
        ));
        $this->app->forgetInstance(RefactorService::class);
    }

    protected function refactor(): RefactorService
    {
        return $this->app->make(RefactorService::class);
    }

    protected function createPhpClass(string $fqcn, string $body): string
    {
        $resolver = $this->app->make(NamespaceResolver::class);
        $path     = $resolver->fqcnToPath($fqcn);

        $parts     = explode('\\', $fqcn);
        array_pop($parts);
        $namespace = implode('\\', $parts);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, "<?php\n\nnamespace {$namespace};\n\n{$body}\n");

        return $path;
    }

    protected function createBladeFile(string $name, string $content): string
    {
        $segments = array_merge(
            [$this->basePath, 'resources', 'views'],
            explode('/', str_replace('\\', '/', $name))
        );
        $path = implode(DIRECTORY_SEPARATOR, $segments);
        $dir  = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);

        return $path;
    }

    private function deleteDirectory(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
    }
}
