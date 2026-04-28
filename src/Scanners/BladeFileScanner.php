<?php

namespace Shan\LaravelRefactor\Scanners;

use Shan\LaravelRefactor\DTOs\ReferenceMatch;

class BladeFileScanner
{
    /**
     * Scan a Blade template for class references.
     *
     * Detects:
     *  - @inject('var', 'App\Models\User')
     *  - Any quoted FQCN string (e.g. in @livewire, component attributes)
     *
     * @return ReferenceMatch[]
     */
    public function scan(string $filePath, string $targetFqcn): array
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return [];
        }

        $matches = [];
        $fqcn = ltrim($targetFqcn, '\\');
        $escapedFqcn = preg_quote($fqcn, '/');
        $lines = explode("\n", $content);

        foreach ($lines as $lineNumber => $line) {
            // @inject('varName', 'App\Models\User')
            if (preg_match('/@inject\s*\(\s*["\'][^"\']*["\']\s*,\s*["\']'.$escapedFqcn.'["\']\s*\)/', $line)) {
                $matches[] = new ReferenceMatch(
                    file: $filePath,
                    line: $lineNumber + 1,
                    type: ReferenceMatch::TYPE_BLADE,
                    matched: $fqcn,
                );
                continue;
            }

            // Any quoted occurrence of the FQCN: 'App\Models\User' or "App\Models\User"
            if (preg_match('/["\']'.str_replace('\\\\', '\\\\\\\\', $escapedFqcn).'["\']/', $line)) {
                $matches[] = new ReferenceMatch(
                    file: $filePath,
                    line: $lineNumber + 1,
                    type: ReferenceMatch::TYPE_BLADE,
                    matched: $fqcn,
                );
            }
        }

        return $matches;
    }
}
