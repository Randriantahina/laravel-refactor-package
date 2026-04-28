<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;

it('extracts old class name', function () {
    $op = new RenameOperation('App\\Models\\User', 'App\\Models\\Member');
    expect($op->oldClass())->toBe('User');
});

it('extracts new class name', function () {
    $op = new RenameOperation('App\\Models\\User', 'App\\Models\\Member');
    expect($op->newClass())->toBe('Member');
});

it('extracts old namespace', function () {
    $op = new RenameOperation('App\\Models\\User', 'App\\Models\\Member');
    expect($op->oldNamespace())->toBe('App\\Models');
});

it('extracts new namespace', function () {
    $op = new RenameOperation('App\\Models\\User', 'App\\Domain\\Users\\User');
    expect($op->newNamespace())->toBe('App\\Domain\\Users');
});

it('detects rename', function () {
    $op = new RenameOperation('App\\Models\\User', 'App\\Models\\Member');
    expect($op->isRename())->toBeTrue();
    expect($op->isMove())->toBeFalse();
});

it('detects move', function () {
    $op = new RenameOperation('App\\Models\\User', 'App\\Domain\\Users\\User');
    expect($op->isMove())->toBeTrue();
    expect($op->isRename())->toBeFalse();
});

it('detects move and rename simultaneously', function () {
    $op = new RenameOperation('App\\Models\\User', 'App\\Domain\\Users\\Member');
    expect($op->isMove())->toBeTrue();
    expect($op->isRename())->toBeTrue();
});
