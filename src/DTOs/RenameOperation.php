<?php

namespace Shan\LaravelRefactor\DTOs;

class RenameOperation
{
    public function __construct(
        public readonly string $oldFqcn,
        public readonly string $newFqcn,
        public readonly bool $dryRun = false,
        public readonly bool $force = false,
    ) {}

    public function oldClass(): string
    {
        return class_basename(str_replace('\\', '/', $this->oldFqcn));
    }

    public function newClass(): string
    {
        return class_basename(str_replace('\\', '/', $this->newFqcn));
    }

    public function oldNamespace(): string
    {
        $parts = explode('\\', $this->oldFqcn);
        array_pop($parts);

        return implode('\\', $parts);
    }

    public function newNamespace(): string
    {
        $parts = explode('\\', $this->newFqcn);
        array_pop($parts);

        return implode('\\', $parts);
    }

    public function isRename(): bool
    {
        return $this->oldClass() !== $this->newClass();
    }

    public function isMove(): bool
    {
        return $this->oldNamespace() !== $this->newNamespace();
    }
}
