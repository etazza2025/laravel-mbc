<?php

declare(strict_types=1);

namespace Undergrace\Mbc\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'mbc:make-tool')]
class MakeMbcToolCommand extends GeneratorCommand
{
    protected $name = 'mbc:make-tool';

    protected $description = 'Create a new MBC tool class';

    protected $type = 'MBC Tool';

    protected function getStub(): string
    {
        return __DIR__ . '/../../../stubs/mbc-tool.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\MbcTools';
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $toolName = $this->option('tool-name')
            ?? Str::snake(class_basename($name));

        $description = $this->option('description')
            ?? 'Description of what this tool does';

        $stub = str_replace('{{ toolName }}', $toolName, $stub);
        $stub = str_replace('{{ description }}', $description, $stub);

        return $stub;
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the tool class'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['tool-name', null, InputOption::VALUE_OPTIONAL, 'The snake_case name for the tool'],
            ['description', 'd', InputOption::VALUE_OPTIONAL, 'Description of the tool'],
        ];
    }
}
