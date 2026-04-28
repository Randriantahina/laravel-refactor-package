# laravel-refactor

Rename or move PHP classes in your Laravel project and automatically update every reference across PHP files, Blade templates, routes, config files, and service providers.

## Installation

```bash
composer require shan/laravel-refactor --dev
```

Laravel auto-discovers the service provider. Optionally publish the config:

```bash
php artisan vendor:publish --tag=laravel-refactor-config
```

## Usage

### Rename a class

```bash
php artisan refactor:rename "App\Models\User" "App\Models\Member"
```

### Move a class to a new namespace

```bash
php artisan refactor:move "App\Models\User" "App\Domain\Users\User"
```

### Preview changes without applying (dry-run)

```bash
php artisan refactor:rename "App\Models\User" "App\Models\Member" --dry-run
```

### Undo the last operation

```bash
php artisan refactor:rename "App\Models\User" "App\Models\Member" --rollback
```

### Override a naming conflict

```bash
php artisan refactor:rename "App\Models\User" "App\Models\Member" --force
```

## What gets updated

| Reference type | Example |
|---|---|
| `use` statements | `use App\Models\User;` |
| `new` instantiations | `new User()` |
| `extends` / `implements` | `class Admin extends User` |
| Type hints & return types | `function store(User $user): User` |
| Static calls | `User::find(1)` |
| `::class` constant | `User::class` |
| Service provider bindings | `$app->bind(User::class, ...)` |
| Route controllers | `[UserController::class, 'index']` |
| Blade `@inject` | `@inject('u', 'App\Models\User')` |
| Quoted FQCN strings in Blade | `'App\Models\User'` |

## Configuration

```php
// config/laravel-refactor.php

return [
    'scan_paths' => [
        'app',
        'routes',
        'config',
        'resources/views',
        'database',
        'tests',
    ],

    'excluded_paths' => [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
    ],

    'backup_path' => 'storage/app/refactor-backups',
];
```

## How it works

1. **Validates** that the source class exists via PSR-4 mappings in `composer.json`
2. **Detects conflicts** — errors out if the target class already exists
3. **Scans** all configured paths using an AST parser ([nikic/php-parser](https://github.com/nikic/PHP-Parser)) for PHP files and regex for Blade templates
4. **Snapshots** all affected files for rollback
5. **Updates** all references in-place
6. **Renames/moves** the source file and updates its `namespace` / `class` declaration
7. Runs `composer dump-autoload` automatically

## Error: class already exists

```
ERROR: Class App\Models\Member already exists at app/Models/Member.php
Rename aborted. Use --force to override (dangerous).
```

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## License

MIT
