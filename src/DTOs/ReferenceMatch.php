<?php

namespace Shan\LaravelRefactor\DTOs;

class ReferenceMatch
{
    public const TYPE_USE       = 'use';
    public const TYPE_NEW       = 'new';
    public const TYPE_EXTENDS   = 'extends';
    public const TYPE_IMPLEMENTS = 'implements';
    public const TYPE_TYPEHINT  = 'typehint';
    public const TYPE_STATIC    = 'static';
    public const TYPE_CLASS_CONST = 'class_const';
    public const TYPE_NAMESPACE = 'namespace';
    public const TYPE_STRING    = 'string';
    public const TYPE_BLADE     = 'blade';

    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $type,
        public readonly string $matched,
    ) {}
}
