<?php

return [
    /*
     * Directories scanned when looking for class references.
     * Paths are resolved relative to base_path().
     */
    'scan_paths' => [
        'app',
        'routes',
        'config',
        'resources/views',
        'database',
        'tests',
    ],

    /*
     * Directories never scanned (vendor, compiled assets, etc.).
     */
    'excluded_paths' => [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
    ],

    /*
     * Where rollback snapshots are stored.
     */
    'backup_path' => 'storage/app/refactor-backups',
];
