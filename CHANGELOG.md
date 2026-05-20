# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-05-20

### Added
- `refactor:rename` command — rename a PHP class and update all references
- `refactor:move` command — move a class to a new namespace
- `--dry-run` flag — preview changes without writing to disk
- `--rollback` flag — undo the last operation
- `--force` flag — override conflict detection
- AST-based scanning (nikic/php-parser) for PHP files
- Regex-based scanning for Blade templates
- Conflict detection — aborts if target class already exists
- Automatic `composer dump-autoload` after each operation
- Support for Laravel 10, 11, 12, and 13
- Support for PHP 8.1, 8.2, 8.3, and 8.4
