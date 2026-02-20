<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Contracts;

use Undergrace\Mbc\DTOs\ToolDefinition;

interface MbcToolInterface
{
    /**
     * Execute the tool with the given validated input.
     */
    public function execute(array $input): mixed;

    /**
     * Get the tool definition for the AI API.
     */
    public function definition(): ToolDefinition;
}
