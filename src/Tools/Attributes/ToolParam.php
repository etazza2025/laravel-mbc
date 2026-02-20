<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Tools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ToolParam
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $description,
        public readonly bool $required = true,
        public readonly ?array $enum = null,
    ) {}
}
