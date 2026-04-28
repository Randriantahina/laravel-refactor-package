<?php

namespace Shan\LaravelRefactor\Services;

class ConflictDetector
{
    public function __construct(private readonly NamespaceResolver $resolver) {}

    /**
     * Returns the conflicting file path if the target FQCN already exists, null otherwise.
     */
    public function findConflict(string $targetFqcn): ?string
    {
        $path = $this->resolver->fqcnToPath($targetFqcn);

        if ($path !== null && file_exists($path)) {
            return $path;
        }

        return null;
    }
}
