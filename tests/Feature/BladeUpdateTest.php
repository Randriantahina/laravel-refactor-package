<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;

beforeEach(fn () => test()->setUpFakeApp());
afterEach(fn () => test()->tearDownFakeApp());

it('updates an @inject directive in a blade file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $blade = $this->createBladeFile('users/show.blade.php',
        "@inject('user', 'App\\Models\\User')\n<p>Hello</p>"
    );

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($blade);
    expect($content)->toContain("'App\\Models\\Member'");
    expect($content)->not->toContain("'App\\Models\\User'");
});

it('updates a double-quoted @inject directive in a blade file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $blade = $this->createBladeFile('users/index.blade.php',
        "@inject('users', \"App\\Models\\User\")"
    );

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($blade);
    expect($content)->toContain('"App\\Models\\Member"');
    expect($content)->not->toContain('"App\\Models\\User"');
});

it('blade file is listed in updatedFiles when changed', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $blade = $this->createBladeFile('users/show.blade.php',
        "@inject('user', 'App\\Models\\User')"
    );

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect($result['updatedFiles'])->toContain($blade);
});

it('does not modify blade files without references', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $blade           = $this->createBladeFile('home.blade.php', '<h1>Welcome</h1>');
    $originalContent = file_get_contents($blade);

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect(file_get_contents($blade))->toBe($originalContent);
});
