<?php

namespace Shan\LaravelRefactor\Commands;

use Illuminate\Console\Command;
use Shan\LaravelRefactor\DTOs\ReferenceMatch;
use Shan\LaravelRefactor\DTOs\RenameOperation;
use Shan\LaravelRefactor\Services\RefactorService;
use Shan\LaravelRefactor\Services\RollbackService;

class MoveCommand extends Command
{
    protected $signature = 'refactor:move
        {old : The fully-qualified class name to move (e.g. App\\Models\\User)}
        {new : The new fully-qualified class name with target namespace (e.g. App\\Domain\\Users\\User)}
        {--dry-run : Preview changes without modifying files}
        {--rollback : Restore files from the last move snapshot}
        {--force : Override if the target class already exists}';

    protected $description = 'Move a PHP class to a new namespace and update all references.';

    public function handle(RefactorService $refactor, RollbackService $rollback): int
    {
        if ($this->option('rollback')) {
            return $this->runRollback($rollback);
        }

        $old = $this->argument('old');
        $new = $this->argument('new');

        $op = new RenameOperation(
            oldFqcn: $old,
            newFqcn: $new,
            dryRun: (bool) $this->option('dry-run'),
            force: (bool) $this->option('force'),
        );

        if (! $op->isMove() && ! $op->isRename()) {
            $this->components->warn('Old and new FQCN are identical — nothing to do.');

            return self::SUCCESS;
        }

        if ($op->dryRun) {
            $this->components->warn('DRY RUN — no files will be modified.');
        }

        $action = $op->isRename() && $op->isMove() ? 'Moving & renaming' : ($op->isMove() ? 'Moving' : 'Renaming');
        $this->components->info("{$action} <fg=yellow>{$old}</> → <fg=green>{$new}</>");
        $this->newLine();

        $result = $refactor->run($op);

        if ($result['error'] !== null) {
            $this->components->error($result['error']);

            return self::FAILURE;
        }

        $this->printMatches($result['matches']);

        if ($op->dryRun) {
            $this->newLine();
            $total = count($result['matches']);
            $this->components->info("{$total} reference(s) found. Run without --dry-run to apply.");

            return self::SUCCESS;
        }

        $this->newLine();
        $updated = count($result['updatedFiles']);
        $refs    = count($result['matches']);
        $this->components->success("{$refs} reference(s) updated across {$updated} file(s).");

        return self::SUCCESS;
    }

    private function runRollback(RollbackService $rollback): int
    {
        $restored = $rollback->restore();

        if (empty($restored)) {
            $this->components->warn('No snapshot found to restore.');

            return self::FAILURE;
        }

        foreach ($restored as $file) {
            $this->line("  <fg=cyan>restored</> {$file}");
        }

        $this->newLine();
        $this->components->success(count($restored).' file(s) restored.');

        return self::SUCCESS;
    }

    /** @param ReferenceMatch[] $matches */
    private function printMatches(array $matches): void
    {
        if (empty($matches)) {
            $this->components->warn('No references found.');

            return;
        }

        $byFile = [];

        foreach ($matches as $match) {
            $byFile[$match->file][] = $match;
        }

        foreach ($byFile as $file => $fileMatches) {
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            $this->line("  <fg=cyan>{$relative}</>");

            foreach ($fileMatches as $match) {
                $type = str_pad($match->type, 12);
                $this->line("    <fg=gray>line {$match->line}</> <fg=yellow>{$type}</> {$match->matched}");
            }
        }
    }
}
