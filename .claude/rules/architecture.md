---
paths:
  - "src/Services/**/*.php"
  - "src/Commands/**/*.php"
---

# Architecture Rules

- `RefactorService::run()` is the single entry point for all operations. Commands call it, nothing else does.
- `NamespaceResolver` reads `composer.json` directly — do not inject the Laravel app's autoloader.
- `RollbackService` stores backups in `storage/app/refactor-backups` by default. The path comes from config, not hardcoded.
- Commands output via `$this->components->*` (Laravel 9+ style). Never use `$this->info()` / `$this->error()` directly.
- `RenameOperation` is immutable (all `readonly` properties). Never add setters.
- `RefactorService` must stay framework-agnostic in its core logic — only the ServiceProvider and Commands are Laravel-coupled.
