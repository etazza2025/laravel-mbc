# MBC — Model Backend Controller

**AI Agent Orchestration Protocol for Laravel**

MBC allows your Laravel backend to orchestrate AI agents as autonomous workers with tools, server-side — no desktop client needed.

## Installation

```bash
composer require undergrace/laravel-mbc
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=mbc-config
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

Add your Anthropic API key to `.env`:

```
ANTHROPIC_API_KEY=your-api-key-here
```

## Quick Start

```php
use Undergrace\Mbc\Facades\Mbc;

$session = Mbc::session('my-agent')
    ->systemPrompt('You are an assistant that helps organize data.')
    ->tools([
        ListItemsTool::class,
        CreateReportTool::class,
    ])
    ->context(['user_id' => 1, 'department' => 'sales'])
    ->config(maxTurns: 20, model: 'claude-sonnet-4-5-20250929')
    ->start('Analyze the sales data and create a summary report.');

$result = $session->result();
echo $result->finalMessage;
```

## Creating Tools

Generate a tool scaffold:

```bash
php artisan mbc:make-tool AnalyzeDataTool
```

Define tools with PHP Attributes:

```php
use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;
use Undergrace\Mbc\Tools\BaseTool;

#[Tool(
    name: 'analyze_data',
    description: 'Analyzes data from the specified table and returns statistics'
)]
class AnalyzeDataTool extends BaseTool
{
    public function __construct(
        private DataRepository $dataRepo,
    ) {}

    #[ToolParam(name: 'table', type: 'string', description: 'Table name to analyze', required: true)]
    #[ToolParam(name: 'metric', type: 'string', description: 'Metric to calculate', enum: ['avg', 'sum', 'count'])]
    public function execute(array $input): mixed
    {
        return $this->dataRepo->analyze($input['table'], $input['metric']);
    }
}
```

## Background Execution

```php
use Undergrace\Mbc\Jobs\RunMbcSessionJob;

$session = Mbc::session('background-agent')
    ->systemPrompt('...')
    ->tools([...])
    ->context([...]);

RunMbcSessionJob::dispatch(
    $session->toSerializable(),
    'Your initial message here'
);
```

## Middleware

```php
use Undergrace\Mbc\Middleware\{LogTurns, CostTracker, RateLimiter};

$session = Mbc::session('with-middleware')
    ->middleware([
        LogTurns::class,
        CostTracker::class,
        RateLimiter::max(30),
    ])
    ->start('...');
```

## Artisan Commands

```bash
# Check session status
php artisan mbc:session-status {uuid}

# Replay a previous session
php artisan mbc:replay {uuid}

# Generate a new tool
php artisan mbc:make-tool {ToolName}
```

## License

MIT - UNDERGRACE LABS
