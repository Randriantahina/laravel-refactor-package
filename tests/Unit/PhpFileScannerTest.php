<?php

use Shan\LaravelRefactor\DTOs\ReferenceMatch;
use Shan\LaravelRefactor\Scanners\PhpFileScanner;

beforeEach(function () {
    $this->scanner = new PhpFileScanner();
    $this->tmpFile = tempnam(sys_get_temp_dir(), 'refactor_test_') . '.php';
});

afterEach(function () {
    if (file_exists($this->tmpFile)) {
        unlink($this->tmpFile);
    }
});

function writePhp(string $path, string $code): void
{
    file_put_contents($path, "<?php\n\n".$code);
}

it('detects use statement', function () {
    writePhp($this->tmpFile, 'use App\\Models\\User;');

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->type)->toBe(ReferenceMatch::TYPE_USE);
});

it('detects new instantiation', function () {
    writePhp($this->tmpFile, "use App\\Models\\User;\n\$u = new User();");

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_NEW);
});

it('detects extends', function () {
    writePhp($this->tmpFile, "use App\\Models\\User;\nclass Admin extends User {}");

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_EXTENDS);
});

it('detects static call', function () {
    writePhp($this->tmpFile, "use App\\Models\\User;\nUser::find(1);");

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_STATIC);
});

it('detects ::class constant', function () {
    writePhp($this->tmpFile, "use App\\Models\\User;\n\$c = User::class;");

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    $types = array_column($matches, 'type');
    expect($types)->toContain(ReferenceMatch::TYPE_CLASS_CONST);
});

it('does not detect unrelated class', function () {
    writePhp($this->tmpFile, 'use App\\Models\\Post;');

    $matches = (new PhpFileScanner())->scan($this->tmpFile, 'App\\Models\\User');

    expect($matches)->toBeEmpty();
});
