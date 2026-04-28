# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/pest --no-coverage

# Run a single test file
./vendor/bin/pest tests/Unit/PhpFileScannerTest.php --no-coverage

# Run tests matching a name
./vendor/bin/pest --filter "detects nullable" --no-coverage

# Regenerate autoload after adding classes
composer dump-autoload
```

## Architecture

This is a standalone Laravel package — there is no Laravel app here. Tests run via `orchestra/testbench` which bootstraps a minimal Laravel app.

### Data flow for a rename/move operation

```
RenameCommand / MoveCommand
  └─ RefactorService::run(RenameOperation)
       ├─ NamespaceResolver   — FQCN ↔ file path via composer.json PSR-4 map
       ├─ ConflictDetector    — checks if target FQCN already exists on disk
       ├─ RollbackService     — snapshots affected files before any write
       ├─ PhpFileScanner      — AST traversal (nikic/php-parser) → ReferenceMatch[]
       ├─ BladeFileScanner    — regex scan of .blade.php files → ReferenceMatch[]
       ├─ PhpFileUpdater      — in-place string replacement of PHP files
       └─ BladeFileUpdater    — in-place string replacement of Blade files
```

### Key design decisions

**PhpFileScanner uses a real AST**, not regex. It runs `NameResolver` first so every `Name` node carries a `resolvedName` attribute with the full FQCN — this is how it resolves short names like `User` to `App\Models\User` without reading the `use` block manually.

**PhpFileUpdater uses targeted regex**, not AST rewriting. This intentionally preserves original formatting. The scan (AST) tells us *what* references exist; the update (regex) changes only those strings.

**`ReferenceMatch->file` starts empty** in the visitor and is hydrated after traversal (`array_map` at the bottom of `PhpFileScanner::scan`). This is an intentional separation so the anonymous visitor class stays stateless regarding file paths.

**`NamespaceResolver` reads `composer.json` directly** (both `autoload` and `autoload-dev` PSR-4 sections) and sorts prefixes by length descending so longer/more-specific prefixes match first.

### Adding a new reference type to scan

1. Add a `TYPE_*` constant to `src/DTOs/ReferenceMatch.php`
2. Add an `if ($node instanceof ...)` block inside `enterNode()` in `src/Scanners/PhpFileScanner.php`
3. Add the corresponding replacement regex in `src/Updaters/PhpFileUpdater.php::replaceReferences()`
4. Add a test in `tests/Unit/PhpFileScannerTest.php`

### Adding support for a new file type (e.g. YAML config)

1. Create `src/Scanners/YamlFileScanner.php` and `src/Updaters/YamlFileUpdater.php`
2. Inject them into `RefactorService` via `LaravelRefactorServiceProvider`
3. Add the file extension to `RefactorService::scanAll()` (the `Finder::name()` call)
4. Handle the new type in `RefactorService::groupByFileAndExtension()`

## Branches & CI

- `dev` — active development branch
- `main` — stable, only merged after CI passes on `dev`

CI (`.github/workflows/tests.yml`) runs the Pest suite against a matrix of **Laravel 10–13 × PHP 8.1–8.4**. Each matrix entry installs the specific `laravel/framework` and `orchestra/testbench` version pair before running tests. A PR is only merged to `main` when all matrix jobs are green.

## Package namespace

`Shan\LaravelRefactor\` maps to `src/`. Tests live under `Shan\LaravelRefactor\Tests\` → `tests/`.
