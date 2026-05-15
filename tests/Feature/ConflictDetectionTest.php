<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;

beforeEach(fn () => test()->setUpFakeApp());
afterEach(fn () => test()->tearDownFakeApp());

it('returns an error when the target class already exists on disk', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');
    $this->createPhpClass('App\\Models\\Member', 'class Member {}');

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect($result['error'])->not->toBeNull();
});

it('error message names the conflicting FQCN', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');
    $this->createPhpClass('App\\Models\\Member', 'class Member {}');

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect($result['error'])->toContain('App\\Models\\Member');
});

it('force flag bypasses conflict detection and overwrites', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');
    $this->createPhpClass('App\\Models\\Member', 'class Member {}');

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member', force: true));

    expect($result['error'])->toBeNull();
});

it('no error when target file does not exist', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect($result['error'])->toBeNull();
});
