<?php

namespace Shan\LaravelRefactor\Updaters;

use Shan\LaravelRefactor\DTOs\RenameOperation;

class PhpFileUpdater
{
    /**
     * Update all class references inside a PHP file.
     * Uses targeted string replacement to avoid re-formatting the whole file.
     */
    public function update(string $filePath, RenameOperation $op): bool
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return false;
        }

        $updated = $this->replaceReferences($content, $op);

        if ($updated === $content) {
            return false;
        }

        file_put_contents($filePath, $updated);

        return true;
    }

    private function replaceReferences(string $content, RenameOperation $op): string
    {
        $oldFqcn = ltrim($op->oldFqcn, '\\');
        $newFqcn = ltrim($op->newFqcn, '\\');
        $oldClass = $op->oldClass();
        $newClass = $op->newClass();
        $oldNs = $op->oldNamespace();
        $newNs = $op->newNamespace();

        // Replace fully-qualified uses: use App\Models\User;
        $content = preg_replace(
            '/\buse\s+'.preg_quote($oldFqcn, '/').'(\s*;|\s+as\s+)/m',
            'use '.$newFqcn.'$1',
            $content,
        );

        // Replace fully-qualified uses in group use: use App\Models\{User, Post}
        if ($oldNs === $newNs) {
            // Only class name changes, namespace same — replace only the short name inside braces
            $content = preg_replace(
                '/\buse\s+'.preg_quote($oldNs, '/').'\\\\\\{([^}]*)\b'.preg_quote($oldClass, '/').'\b([^}]*)\\}/m',
                'use '.$oldNs.'\\{$1'.$newClass.'$2}',
                $content,
            );
        }

        // Replace class declaration: class User / class OldName
        $content = preg_replace(
            '/\b(class|interface|trait|enum)\s+'.preg_quote($oldClass, '/').'\b/',
            '$1 '.$newClass,
            $content,
        );

        // Replace namespace declaration if it changed
        if ($oldNs !== $newNs) {
            $content = preg_replace(
                '/^namespace\s+'.preg_quote($oldNs, '/').'\s*;/m',
                'namespace '.$newNs.';',
                $content,
            );
        }

        // Replace short name usages (new User, User::, extends User, implements User, type hints)
        // Only when the class name changes and the file already imports it (safe assumption after use replacement)
        if ($oldClass !== $newClass) {
            // new OldClass(
            $content = preg_replace('/\bnew\s+'.preg_quote($oldClass, '/').'\b/', 'new '.$newClass, $content);

            // OldClass:: (static calls, ::class)
            $content = preg_replace('/\b'.preg_quote($oldClass, '/').'::/m', $newClass.'::', $content);

            // extends OldClass / implements OldClass, OtherClass
            $content = preg_replace('/\b(extends|implements)(\s+(?:[A-Za-z0-9_\\\\]+,\s*)*)'.preg_quote($oldClass, '/').'\b/', '$1$2'.$newClass, $content);

            // function foo(OldClass $param) / : OldClass return type
            $content = preg_replace('/([(:,\|&]\s*)'.preg_quote($oldClass, '/').'\b/', '$1'.$newClass, $content);
            $content = preg_replace('/\b'.preg_quote($oldClass, '/').'\s*\|/', $newClass.'|', $content);
        }

        return $content;
    }

    /**
     * Update namespace and class name declarations in the class's own file.
     */
    public function updateSelf(string $filePath, RenameOperation $op): void
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return;
        }

        $oldClass = $op->oldClass();
        $newClass = $op->newClass();
        $oldNs = $op->oldNamespace();
        $newNs = $op->newNamespace();

        // Update namespace declaration
        if ($oldNs !== $newNs) {
            $content = preg_replace(
                '/^namespace\s+'.preg_quote($oldNs, '/').'\s*;/m',
                'namespace '.$newNs.';',
                $content,
            );
        }

        // Update class/interface/trait/enum name
        if ($oldClass !== $newClass) {
            $content = preg_replace(
                '/\b(class|interface|trait|enum)\s+'.preg_quote($oldClass, '/').'\b/',
                '$1 '.$newClass,
                $content,
            );
        }

        file_put_contents($filePath, $content);
    }
}
