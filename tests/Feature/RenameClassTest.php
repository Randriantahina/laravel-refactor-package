<?php

use Shan\LaravelRefactor\DTOs\RenameOperation;

beforeEach(fn () => test()->setUpFakeApp());
afterEach(fn () => test()->tearDownFakeApp());

it('renames the source file on disk', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $resolver = $this->app->make(\Shan\LaravelRefactor\Services\NamespaceResolver::class);
    $newPath  = $resolver->fqcnToPath('App\\Models\\Member');

    expect(file_exists($newPath))->toBeTrue();
    expect(file_exists($source))->toBeFalse();
});

it('updates the class declaration in the renamed file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $resolver = $this->app->make(\Shan\LaravelRefactor\Services\NamespaceResolver::class);
    $content  = file_get_contents($resolver->fqcnToPath('App\\Models\\Member'));

    expect($content)->toContain('class Member');
    expect($content)->not->toContain('class User');
});

it('updates a use statement in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Http\\Controllers\\UserController', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserController {}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('use App\\Models\\Member;');
    expect($content)->not->toContain('use App\\Models\\User;');
});

it('updates a new instantiation in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function make(): User { return new User(); }',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('new Member()');
    expect($content)->not->toContain('new User()');
});

it('updates an extends clause in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Models\\AdminUser', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class AdminUser extends User {}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('extends Member');
    expect($content)->not->toContain('extends User');
});

it('updates a static call in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User { public static function find(int $id): ?self { return null; } }');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function get(int $id) { return User::find($id); }',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('Member::find(');
    expect($content)->not->toContain('User::find(');
});

it('updates a ::class reference in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function className(): string { return User::class; }',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('Member::class');
    expect($content)->not->toContain('User::class');
});

it('updates a parameter type hint in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function handle(User $user): void {}',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('(Member $user)');
    expect($content)->not->toContain('(User $user)');
});

it('updates a return type hint in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function get(): User { return new User(); }',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('): Member');
    expect($content)->not->toContain('): User');
});

it('updates a nullable type hint in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function find(int $id): ?User { return null; }',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('?Member');
    expect($content)->not->toContain('?User');
});

it('updates a union type hint in a referencing file', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        '',
        'class UserService {',
        '    public function find(int $id): User|null { return null; }',
        '}',
    ]));

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    $content = file_get_contents($referrer);
    expect($content)->toContain('Member|null');
    expect($content)->not->toContain('User|null');
});

it('does not modify files without references', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $unrelated = $this->createPhpClass('App\\Models\\Post', 'class Post {}');
    $original  = file_get_contents($unrelated);

    $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect(file_get_contents($unrelated))->toBe($original);
});

it('returns the list of updated files', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $referrer = $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        'class UserService {}',
    ]));

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect($result['error'])->toBeNull();
    expect($result['updatedFiles'])->toContain($referrer);
});

it('returns all reference matches in the result', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->createPhpClass('App\\Services\\UserService', implode("\n", [
        'use App\\Models\\User;',
        'class UserService {}',
    ]));

    $result = $this->refactor()->run(new RenameOperation('App\\Models\\User', 'App\\Models\\Member'));

    expect($result['error'])->toBeNull();
    expect($result['matches'])->not->toBeEmpty();
});
