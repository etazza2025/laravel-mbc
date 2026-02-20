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

Add your API keys to `.env` depending on the provider you want to use:

```env
# Default provider (anthropic, openai, or openrouter)
MBC_PROVIDER=anthropic

# Anthropic (Claude)
ANTHROPIC_API_KEY=sk-ant-...

# OpenAI (GPT-4o, o1, o3)
OPENAI_API_KEY=sk-...

# OpenRouter (200+ models: Claude, GPT, Gemini, Llama, Mistral, DeepSeek...)
OPENROUTER_API_KEY=sk-or-...
```

## Supported Providers

| Provider | Models | Tool Use | Config Key |
|----------|--------|----------|------------|
| **Anthropic** | Claude Sonnet 4.5, Opus, Haiku | Native | `anthropic` |
| **OpenAI** | GPT-4o, o1, o3 | Native | `openai` |
| **OpenRouter** | 200+ models from all providers | OpenAI-compatible | `openrouter` |

Set the default provider in `config/mbc.php`:

```php
'default_provider' => env('MBC_PROVIDER', 'anthropic'),
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

### Using Different Providers Per Session

```php
// Claude via Anthropic (direct)
Mbc::session('designer')
    ->config(model: 'claude-sonnet-4-5-20250929')
    ->start('...');

// GPT-4o via OpenAI
Mbc::session('copywriter')
    ->config(model: 'gpt-4o')
    ->start('...');

// Any model via OpenRouter
Mbc::session('analyst')
    ->config(model: 'anthropic/claude-sonnet-4')
    ->start('...');

// Gemini via OpenRouter
Mbc::session('researcher')
    ->config(model: 'google/gemini-2.5-pro')
    ->start('...');
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
