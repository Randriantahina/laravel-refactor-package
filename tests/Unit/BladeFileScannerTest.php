<?php

use Shan\LaravelRefactor\DTOs\ReferenceMatch;
use Shan\LaravelRefactor\Scanners\BladeFileScanner;

beforeEach(function () {
    $this->scanner = new BladeFileScanner();
    $this->tmpFile = tempnam(sys_get_temp_dir(), 'blade_test_').'.blade.php';
});

afterEach(function () {
    if (file_exists($this->tmpFile)) {
        unlink($this->tmpFile);
    }
});

it('detects @inject directive', function () {
    file_put_contents($this->tmpFile, "@inject('user', 'App\\\\Models\\\\User')");

    $matches = (new BladeFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->type)->toBe(ReferenceMatch::TYPE_BLADE);
});

it('detects quoted FQCN string', function () {
    file_put_contents($this->tmpFile, "<livewire:component class=\"App\\\\Models\\\\User\" />");

    $matches = (new BladeFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    expect($matches)->not->toBeEmpty();
});

it('ignores unrelated class', function () {
    file_put_contents($this->tmpFile, "@inject('post', 'App\\\\Models\\\\Post')");

    $matches = (new BladeFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    expect($matches)->toBeEmpty();
});
