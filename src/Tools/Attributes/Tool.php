<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Tools\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Tool
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
    ) {}
}
