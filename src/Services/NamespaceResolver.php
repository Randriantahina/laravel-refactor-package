<?php

namespace Shan\LaravelRefactor\Services;

class NamespaceResolver
{
    /** @var array<string, string> */
    private array $psr4Map = [];

    public function __construct(private readonly string $basePath)
    {
        $this->loadPsr4Map();
    }

    /**
     * Convert a fully-qualified class name to an absolute file path.
     */
    public function fqcnToPath(string $fqcn): ?string
    {
        $fqcn = ltrim($fqcn, '\\');

        foreach ($this->psr4Map as $prefix => $dir) {
            if (str_starts_with($fqcn, $prefix)) {
                $relative = substr($fqcn, strlen($prefix));
                $file = $dir.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php';

                return $file;
            }
        }

        return null;
    }

    /**
     * Convert an absolute file path to a fully-qualified class name.
     */
    public function pathToFqcn(string $path): ?string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        foreach ($this->psr4Map as $prefix => $dir) {
            $dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir);

            if (str_starts_with($path, $dir)) {
                $relative = substr($path, strlen($dir) + 1);
                $relative = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

                if (str_ends_with($relative, '.php')) {
                    $relative = substr($relative, 0, -4);
                }

                return $prefix.$relative;
            }
        }

        return null;
    }

    /**
     * Derive the expected namespace for a file path based on PSR-4 mappings.
     */
    public function pathToNamespace(string $path): ?string
    {
        $fqcn = $this->pathToFqcn($path);

        if ($fqcn === null) {
            return null;
        }

        $parts = explode('\\', $fqcn);
        array_pop($parts);

        return implode('\\', $parts);
    }

    private function loadPsr4Map(): void
    {
        $composerPath = $this->basePath.DIRECTORY_SEPARATOR.'composer.json';

        if (! file_exists($composerPath)) {
            return;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        $mappings = array_merge(
            $composer['autoload']['psr-4'] ?? [],
            $composer['autoload-dev']['psr-4'] ?? [],
        );

        foreach ($mappings as $prefix => $dirs) {
            foreach ((array) $dirs as $dir) {
                $absDir = $this->basePath.DIRECTORY_SEPARATOR.rtrim(str_replace('/', DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR);
                $this->psr4Map[rtrim($prefix, '\\'). '\\'] = $absDir;
            }
        }

        // Sort by prefix length descending so longer prefixes match first
        uksort($this->psr4Map, fn ($a, $b) => strlen($b) - strlen($a));
    }
}
