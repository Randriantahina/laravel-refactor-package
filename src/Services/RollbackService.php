<?php

namespace Shan\LaravelRefactor\Services;

class RollbackService
{
    private string $snapshotDir;

    public function __construct(private readonly string $basePath)
    {
        $backupPath = config('laravel-refactor.backup_path', 'storage/app/refactor-backups');
        $this->snapshotDir = $this->basePath.DIRECTORY_SEPARATOR.ltrim($backupPath, '/\\');
    }

    /**
     * Save snapshots of all files that will be modified.
     *
     * @param string[] $files
     */
    public function snapshot(string $operationId, array $files): void
    {
        $dir = $this->snapshotDir.DIRECTORY_SEPARATOR.$operationId;

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manifest = [];

        foreach ($files as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $hash = md5($file);
            $dest = $dir.DIRECTORY_SEPARATOR.$hash.'.bak';
            copy($file, $dest);
            $manifest[$hash] = $file;
        }

        file_put_contents($dir.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        file_put_contents($this->snapshotDir.DIRECTORY_SEPARATOR.'latest.txt', $operationId);
    }

    /**
     * Restore all files from the latest (or given) snapshot.
     */
    public function restore(?string $operationId = null): array
    {
        $operationId ??= $this->latestOperationId();

        if ($operationId === null) {
            return [];
        }

        $dir = $this->snapshotDir.DIRECTORY_SEPARATOR.$operationId;
        $manifestFile = $dir.DIRECTORY_SEPARATOR.'manifest.json';

        if (! file_exists($manifestFile)) {
            return [];
        }

        $manifest = json_decode(file_get_contents($manifestFile), true);
        $restored = [];

        foreach ($manifest as $hash => $originalPath) {
            $backup = $dir.DIRECTORY_SEPARATOR.$hash.'.bak';

            if (file_exists($backup)) {
                // Recreate directory if needed (in case file was moved)
                $targetDir = dirname($originalPath);
                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }

                copy($backup, $originalPath);
                $restored[] = $originalPath;
            }
        }

        return $restored;
    }

    public function latestOperationId(): ?string
    {
        $file = $this->snapshotDir.DIRECTORY_SEPARATOR.'latest.txt';

        if (! file_exists($file)) {
            return null;
        }

        return trim(file_get_contents($file));
    }
}
