<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;
use Shan\LaravelRefactor\Services\NamespaceResolver;
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

    $snapshotDir  = $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'refactor-backups' . DIRECTORY_SEPARATOR . $operationId;
    $manifestPath = $snapshotDir . DIRECTORY_SEPARATOR . 'manifest.json';

    expect(file_exists($manifestPath))->toBeTrue();

    $data  = json_decode(file_get_contents($manifestPath), true);
    $paths = array_values($data['files']);

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

it('deletes the target file on rollback', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $resolver   = $this->app->make(NamespaceResolver::class);
    $targetPath = $resolver->fqcnToPath('App\\Models\\Member');

    expect(file_exists($targetPath))->toBeTrue();

    $this->refactor()->rollback();

    expect(file_exists($targetPath))->toBeFalse();
});

it('restores overwritten target file content when --force was used', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');
    $target          = $this->createPhpClass('App\\Models\\Member', 'class Member { /* original */ }');
    $originalContent = file_get_contents($target);

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member', force: true));

    expect(file_get_contents($target))->not->toBe($originalContent);

    $this->refactor()->rollback();

    expect(file_get_contents($target))->toBe($originalContent);
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
