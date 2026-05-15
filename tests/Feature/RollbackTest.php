<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;
use Shan\LaravelRefactor\Services\RollbackService;

beforeEach(fn () => test()->setUpFakeApp());
afterEach(fn () => test()->tearDownFakeApp());

it('creates a snapshot directory before modifying files', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $rollback    = $this->app->make(RollbackService::class);
    $operationId = $rollback->latestOperationId();

    expect($operationId)->not->toBeNull();
});

it('snapshot manifest contains all affected file paths', function () {
    $source   = $this->createPhpClass('App\\Models\\User', 'class User {}');
    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        'class UserService {}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $rollback    = $this->app->make(RollbackService::class);
    $operationId = $rollback->latestOperationId();

    $snapshotDir  = $this->basePath . '/storage/app/refactor-backups/' . $operationId;
    $manifestPath = $snapshotDir . '/manifest.json';

    expect(file_exists($manifestPath))->toBeTrue();

    $manifest = json_decode(file_get_contents($manifestPath), true);
    $paths    = array_values($manifest);

    expect($paths)->toContain($source);
    expect($paths)->toContain($referrer);
});

it('restores original file content after rollback', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer        = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        'class UserService {}',
    ]));
    $originalContent = file_get_contents($referrer);

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));
    $this->refactor()->rollback();

    expect(file_get_contents($referrer))->toBe($originalContent);
});

it('restores the source file to its original location after rollback', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect(file_exists($source))->toBeFalse();

    $this->refactor()->rollback();

    expect(file_exists($source))->toBeTrue();
});

it('returns the list of restored files', function () {
    $source   = $this->createPhpClass('App\\Models\\User', 'class User {}');
    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        'class UserService {}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));
    $restored = $this->refactor()->rollback();

    expect($restored)->toContain($source);
    expect($restored)->toContain($referrer);
});

it('rollback returns empty array when no snapshot exists', function () {
    $restored = $this->refactor()->rollback();

    expect($restored)->toBeEmpty();
});
