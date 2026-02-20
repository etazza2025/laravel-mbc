<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Tools;

use ReflectionClass;
use RuntimeException;
use Undergrace\Mbc\Contracts\MbcToolInterface;
use Undergrace\Mbc\DTOs\ToolDefinition;
use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;

abstract class BaseTool implements MbcToolInterface
{
    abstract public function execute(array $input): mixed;

    /**
     * Build the tool definition by reflecting PHP Attributes on the class and execute() method.
     */
    public function definition(): ToolDefinition
    {
        $reflection = new ReflectionClass($this);

        $toolAttributes = $reflection->getAttributes(Tool::class);

        if (empty($toolAttributes)) {
            throw new RuntimeException(
                'Tool class ' . static::class . ' must have the #[Tool] attribute.'
            );
        }

        /** @var Tool $tool */
        $tool = $toolAttributes[0]->newInstance();

        $executeMethod = $reflection->getMethod('execute');
        $paramAttributes = $executeMethod->getAttributes(ToolParam::class);

        $properties = [];
        $required = [];

        foreach ($paramAttributes as $paramAttr) {
            /** @var ToolParam $param */
            $param = $paramAttr->newInstance();

            $prop = [
                'type' => $param->type,
                'description' => $param->description,
            ];

            if ($param->enum !== null) {
                $prop['enum'] = $param->enum;
            }

            $properties[$param->name] = $prop;

            if ($param->required) {
                $required[] = $param->name;
            }
        }

        return new ToolDefinition(
            name: $tool->name,
            description: $tool->description,
            inputSchema: [
                'type' => 'object',
                'properties' => empty($properties) ? new \stdClass() : $properties,
                'required' => $required,
            ],
        );
    }
}
