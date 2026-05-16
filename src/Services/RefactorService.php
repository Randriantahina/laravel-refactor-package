<?php

namespace Shan\LaravelRefactor\Services;

use Shan\LaravelRefactor\DTOs\ReferenceMatch;
use Shan\LaravelRefactor\DTOs\RenameOperation;
use Shan\LaravelRefactor\Scanners\BladeFileScanner;
use Shan\LaravelRefactor\Scanners\PhpFileScanner;
use Shan\LaravelRefactor\Updaters\BladeFileUpdater;
use Shan\LaravelRefactor\Updaters\PhpFileUpdater;
use Symfony\Component\Finder\Finder;

class RefactorService
{
    public function __construct(
        private readonly NamespaceResolver $namespaceResolver,
        private readonly ConflictDetector $conflictDetector,
        private readonly RollbackService $rollbackService,
        private readonly PhpFileScanner $phpScanner,
        private readonly BladeFileScanner $bladeScanner,
        private readonly PhpFileUpdater $phpUpdater,
        private readonly BladeFileUpdater $bladeUpdater,
        private readonly string $basePath,
    ) {}

    /**
     * Run the full rename/move operation.
     *
     * @return array{matches: ReferenceMatch[], updatedFiles: string[], error: string|null}
     */
    public function run(RenameOperation $op): array
    {
        // 1. Validate source
        $sourcePath = $this->namespaceResolver->fqcnToPath($op->oldFqcn);

        if ($sourcePath === null || ! file_exists($sourcePath)) {
            return $this->failure("Class [{$op->oldFqcn}] not found. Check the FQCN and composer.json PSR-4 mappings.");
        }

        // 2. Detect conflict
        if (! $op->force) {
            $conflict = $this->conflictDetector->findConflict($op->newFqcn);

            if ($conflict !== null) {
                return $this->failure("Class [{$op->newFqcn}] already exists at [{$conflict}]. Use --force to override.");
            }
        }

        // 3. Scan all files
        $allMatches = $this->scanAll($op->oldFqcn);

        if ($op->dryRun) {
            return ['matches' => $allMatches, 'updatedFiles' => [], 'error' => null];
        }

        // 4. Snapshot for rollback
        $affectedFiles = array_unique(array_map(fn (ReferenceMatch $m) => $m->file, $allMatches));
        $affectedFiles[] = $sourcePath;

        $targetPath   = $this->namespaceResolver->fqcnToPath($op->newFqcn);
        $createdFiles = [];

        if ($targetPath !== null && $sourcePath !== $targetPath) {
            if ($op->force && file_exists($targetPath)) {
                // --force: target exists and will be overwritten — back it up so rollback can restore it.
                $affectedFiles[] = $targetPath;
            } else {
                // Normal case: target file will be newly created — mark it for deletion on rollback.
                $createdFiles[] = $targetPath;
            }
        }

        $operationId = date('YmdHis').'_'.substr(md5($op->oldFqcn), 0, 6);
        $this->rollbackService->snapshot($operationId, array_unique($affectedFiles), $createdFiles);

        // 5. Update references in other files
        $updatedFiles = [];
        $filesByType = $this->groupByFileAndExtension($allMatches);

        foreach ($filesByType['php'] ?? [] as $file => $matches) {
            if ($this->phpUpdater->update($file, $op)) {
                $updatedFiles[] = $file;
            }
        }

        foreach ($filesByType['blade'] ?? [] as $file => $matches) {
            if ($this->bladeUpdater->update($file, $op)) {
                $updatedFiles[] = $file;
            }
        }

        // 6. Rename/move the source file (targetPath already computed above)

        if ($targetPath !== null) {
            $targetDir = dirname($targetPath);

            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $this->phpUpdater->updateSelf($sourcePath, $op);

            if ($sourcePath !== $targetPath) {
                rename($sourcePath, $targetPath);
            }

            $updatedFiles[] = $targetPath;
        }

        // 7. composer dump-autoload
        $this->dumpAutoload();

        return ['matches' => $allMatches, 'updatedFiles' => array_unique($updatedFiles), 'error' => null];
    }

    /**
     * Restore the previous state.
     *
     * @return string[]
     */
    public function rollback(): array
    {
        return $this->rollbackService->restore();
    }

    /**
     * Scan all configured paths for references to $fqcn.
     *
     * @return ReferenceMatch[]
     */
    public function scanAll(string $fqcn): array
    {
        $scanPaths = config('laravel-refactor.scan_paths', ['app', 'routes', 'config', 'resources/views', 'database', 'tests']);
        $excludedPaths = config('laravel-refactor.excluded_paths', ['vendor', 'node_modules', 'storage', 'bootstrap/cache']);

        $absScanPaths = array_filter(
            array_map(fn ($p) => $this->basePath.DIRECTORY_SEPARATOR.ltrim($p, '/\\'), $scanPaths),
            'is_dir',
        );

        $absExcludedPaths = array_map(fn ($p) => $this->basePath.DIRECTORY_SEPARATOR.ltrim($p, '/\\'), $excludedPaths);

        if (empty($absScanPaths)) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->in(array_values($absScanPaths));

        foreach ($absExcludedPaths as $excluded) {
            if (is_dir($excluded)) {
                $finder->exclude(basename($excluded));
            }
        }

        $finder->name(['*.php', '*.blade.php']);

        $matches = [];

        foreach ($finder as $file) {
            $path = $file->getRealPath();
            $ext  = $file->getExtension();

            if (str_ends_with($path, '.blade.php')) {
                $matches = array_merge($matches, $this->bladeScanner->scan($path, $fqcn));
            } elseif ($ext === 'php') {
                $matches = array_merge($matches, $this->phpScanner->scan($path, $fqcn));
            }
        }

        return $matches;
    }

    /**
     * @param ReferenceMatch[] $matches
     * @return array<string, array<string, ReferenceMatch[]>>
     */
    private function groupByFileAndExtension(array $matches): array
    {
        $grouped = [];

        foreach ($matches as $match) {
            $ext = str_ends_with($match->file, '.blade.php') ? 'blade' : 'php';
            $grouped[$ext][$match->file][] = $match;
        }

        return $grouped;
    }

    private function dumpAutoload(): void
    {
        exec('composer dump-autoload --quiet 2>&1', $output, $code);
    }

    private function failure(string $message): array
    {
        return ['matches' => [], 'updatedFiles' => [], 'error' => $message];
    }
}
