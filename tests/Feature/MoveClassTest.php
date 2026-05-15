<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;
use Shan\LaravelRefactor\Services\NamespaceResolver;

beforeEach(fn () => test()->setUpFakeApp());
afterEach(fn () => test()->tearDownFakeApp());

it('moves a class file to the new namespace directory', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Domain\\Users\\User'));

    $resolver = $this->app->make(NamespaceResolver::class);
    $newPath  = $resolver->fqcnToPath('App\\Domain\\Users\\User');

    expect(file_exists($newPath))->toBeTrue();
});

it('deletes the original file after a move', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Domain\\Users\\User'));

    expect(file_exists($source))->toBeFalse();
});

it('updates the namespace declaration in the moved file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Domain\\Users\\User'));

    $resolver = $this->app->make(NamespaceResolver::class);
    $content  = file_get_contents($resolver->fqcnToPath('App\\Domain\\Users\\User'));

    expect($content)->toContain('namespace App\\Domain\\Users;');
    expect($content)->not->toContain('namespace App\\Models;');
});

it('creates the target directory if it does not exist', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Domain\\Deeply\\Nested\\User'));

    $resolver = $this->app->make(NamespaceResolver::class);
    $newPath  = $resolver->fqcnToPath('App\\Domain\\Deeply\\Nested\\User');

    expect($result['error'])->toBeNull();
    expect(file_exists($newPath))->toBeTrue();
    expect(is_dir(dirname($newPath)))->toBeTrue();
});

it('updates a use statement in a referencing file after a move', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function get(): User { return new User(); }',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Domain\\Users\\User'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('use App\\Domain\\Users\\User;');
    expect($content)->not->toContain('use App\\Models\\User;');
});

it('handles simultaneous rename and move', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Domain\\Users\\Member'));

    $resolver = $this->app->make(NamespaceResolver::class);
    $newPath  = $resolver->fqcnToPath('App\\Domain\\Users\\Member');

    expect(file_exists($newPath))->toBeTrue();
    expect(file_exists($source))->toBeFalse();

    $content = file_get_contents($newPath);
    expect($content)->toContain('namespace App\\Domain\\Users;');
    expect($content)->toContain('class Member');
});

it('updates use statement when only namespace changes and class name stays', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function get(): User { return new User(); }',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Domain\\User'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('use App\\Domain\\User;');
    expect($content)->not->toContain('use App\\Models\\User;');
    expect($content)->toContain('new User()');
});
