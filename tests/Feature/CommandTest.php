<?php

use Shan\LaravelRefactor\Services\NamespaceResolver;

beforeEach(fn () => test()->setUpFakeApp());
afterEach(fn () => test()->tearDownFakeApp());

// ─── refactor:rename ──────────────────────────────────────────────────────────

it('refactor:rename exits with success when source exists', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->artisan('refactor:rename', [
        'old' => 'App\\Models\\User',
        'new' => 'App\\Models\\Member',
    ])->assertExitCode(0);
});

it('refactor:rename renames the file on disk', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->artisan('refactor:rename', [
        'old' => 'App\\Models\\User',
        'new' => 'App\\Models\\Member',
    ])->run();

    $resolver = $this->app->make(NamespaceResolver::class);
    expect(file_exists($resolver->fqcnToPath('App\\Models\\Member')))->toBeTrue();
    expect(file_exists($source))->toBeFalse();
});

it('refactor:rename exits with failure when source class is missing', function () {
    $this->artisan('refactor:rename', [
        'old' => 'App\\Models\\Ghost',
        'new' => 'App\\Models\\Member',
    ])->assertExitCode(1);
});

it('refactor:rename --dry-run does not rename the file', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->artisan('refactor:rename', [
        'old'       => 'App\\Models\\User',
        'new'       => 'App\\Models\\Member',
        '--dry-run' => true,
    ])->run();

    expect(file_exists($source))->toBeTrue();
});

it('refactor:rename --rollback restores files and exits success', function () {
    $source          = $this->createPhpClass('App\\Models\\User', 'class User {}');
    $originalContent = file_get_contents($source);

    $this->artisan('refactor:rename', [
        'old' => 'App\\Models\\User',
        'new' => 'App\\Models\\Member',
    ])->run();

    $this->artisan('refactor:rename', [
        'old'        => 'App\\Models\\User',
        'new'        => 'App\\Models\\Member',
        '--rollback' => true,
    ])->assertExitCode(0);

    expect(file_exists($source))->toBeTrue();
    expect(file_get_contents($source))->toBe($originalContent);
});

it('refactor:rename --rollback exits failure when no snapshot exists', function () {
    $this->artisan('refactor:rename', [
        'old'        => 'App\\Models\\User',
        'new'        => 'App\\Models\\Member',
        '--rollback' => true,
    ])->assertExitCode(1);
});

// ─── refactor:move ────────────────────────────────────────────────────────────

it('refactor:move exits with success when source exists', function () {
    $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->artisan('refactor:move', [
        'old' => 'App\\Models\\User',
        'new' => 'App\\Domain\\Users\\User',
    ])->assertExitCode(0);
});

it('refactor:move moves the file on disk', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->artisan('refactor:move', [
        'old' => 'App\\Models\\User',
        'new' => 'App\\Domain\\Users\\User',
    ])->run();

    $resolver = $this->app->make(NamespaceResolver::class);
    expect(file_exists($resolver->fqcnToPath('App\\Domain\\Users\\User')))->toBeTrue();
    expect(file_exists($source))->toBeFalse();
});

it('refactor:move --dry-run does not move the file', function () {
    $source = $this->createPhpClass('App\\Models\\User', 'class User {}');

    $this->artisan('refactor:move', [
        'old'       => 'App\\Models\\User',
        'new'       => 'App\\Domain\\Users\\User',
        '--dry-run' => true,
    ])->run();

    expect(file_exists($source))->toBeTrue();
});

it('refactor:move exits with failure when source class is missing', function () {
    $this->artisan('refactor:move', [
        'old' => 'App\\Models\\Ghost',
        'new' => 'App\\Domain\\Users\\Ghost',
    ])->assertExitCode(1);
});
