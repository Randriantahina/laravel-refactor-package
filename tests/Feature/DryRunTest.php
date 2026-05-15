<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;
use Shan\LaravelRefactor\Services\RollbackService;

beforeEach(fn () => test()->setUpFakeApp());
afterEach(fn () => test()->tearDownFakeApp());

it('returns matches without modifying any files on dry run', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        'class UserService {}',
    ]));
    $originalContent = file_get_contents($referrer);

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member', dryRun: true));

    expect($result['error'])->toBeNull();
    expect($result['matches'])->not->toBeEmpty();
    expect(file_get_contents($referrer))->toBe($originalContent);
});

it('does not rename the source file on dry run', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member', dryRun: true));

    expect(file_exists($source))->toBeTrue();
});

it('does not create a rollback snapshot on dry run', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member', dryRun: true));

    $rollback = $this->app->make(RollbackService::class);
    expect($rollback->latestOperationId())->toBeNull();
});

it('returns zero updatedFiles on dry run', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        'class UserService {}',
    ]));

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member', dryRun: true));

    expect($result['updatedFiles'])->toBeEmpty();
});
