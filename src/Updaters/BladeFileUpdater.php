<?php

namespace Shan\LaravelRefactor\Updaters;

use Shan\LaravelRefactor\DTOs\RenameOperation;

class BladeFileUpdater
{
    public function update(string $filePath, RenameOperation $op): bool
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return false;
        }

        $oldFqcn = ltrim($op->oldFqcn, '\\');
        $newFqcn = ltrim($op->newFqcn, '\\');

        // Replace all quoted occurrences of the old FQCN
        $updated = str_replace(
            ["'{$oldFqcn}'", "\"{$oldFqcn}\""],
            ["'{$newFqcn}'", "\"{$newFqcn}\""],
            $content,
        );

        if ($updated === $content) {
            return false;
        }

        file_put_contents($filePath, $updated);

        return true;
    }
}
