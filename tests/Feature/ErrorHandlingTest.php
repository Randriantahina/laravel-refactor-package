<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;

beforeEach(fn () => test()->setUpFakeApp());
afterEach(fn () => test()->tearDownFakeApp());

it('returns an error when the source class file does not exist', function () {
    $result = $this->refactor()->run(new RenameOperation('App\\Models\\Ghost', 'App\\Models\\Member'));

    expect($result['error'])->not->toBeNull();
});

it('error message names the missing FQCN', function () {
    $result = $this->refactor()->run(new RenameOperation('App\\Models\\Ghost', 'App\\Models\\Member'));

    expect($result['error'])->toContain('App\\Models\\Ghost');
});

it('returns error when source FQCN has no PSR-4 mapping', function () {
    $result = $this->refactor()->run(new RenameOperation('Unknown\\Vendor\\Foo', 'Unknown\\Vendor\\Bar'));

    expect($result['error'])->not->toBeNull();
});

it('returns empty matches and updatedFiles on error', function () {
    $result = $this->refactor()->run(new RenameOperation('App\\Models\\Ghost', 'App\\Models\\Member'));

    expect($result['matches'])->toBeEmpty();
    expect($result['updatedFiles'])->toBeEmpty();
});
