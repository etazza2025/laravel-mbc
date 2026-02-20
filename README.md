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
| **Anthropic** | Claude Sonnet 4.5, Opus 4, Haiku 3.5 | Native | `anthropic` |
| **OpenAI** | GPT-4o, o1, o3, o3-mini | Native | `openai` |
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
    ->start('Design the database schema.');

// GPT-4o via OpenAI
Mbc::session('copywriter')
    ->config(model: 'gpt-4o')
    ->start('Write compelling product descriptions.');

// Any model via OpenRouter
Mbc::session('analyst')
    ->config(model: 'anthropic/claude-sonnet-4')
    ->start('Analyze quarterly data.');

// Gemini via OpenRouter
Mbc::session('researcher')
    ->config(model: 'google/gemini-2.5-pro')
    ->start('Research market trends.');
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

## Inter-Agent Communication

MBC provides three patterns for multi-agent collaboration:

### Pipeline (Sequential Chaining)

Each agent receives the previous agent's result as context. Ideal for workflows where Agent A's output feeds into Agent B.

```php
use Undergrace\Mbc\Core\MbcPipeline;

$result = MbcPipeline::create()
    ->pipe($architectSession, 'Design the database schema')
    ->pipe($backendSession, 'Implement the API based on the schema')
    ->pipe($frontendSession, 'Create the UI components for the API')
    ->run();

if ($result->successful()) {
    echo $result->final()->finalMessage;
}

echo "Total cost: $" . $result->totalCost();
echo "Stages: " . $result->stageCount();
```

### Orchestrator (Parallel Execution)

Run multiple agents simultaneously and collect results. Ideal for independent tasks that can execute in parallel.

```php
use Undergrace\Mbc\Core\MbcOrchestrator;

// Async via queue
$orchestrator = MbcOrchestrator::create('build-site')
    ->agent($designerSession, 'Design the layout')
    ->agent($copywriterSession, 'Write the content')
    ->agent($seoSession, 'Optimize for search engines')
    ->dispatch();

// Check progress
$progress = $orchestrator->progress();
// ['total' => 3, 'completed' => 2, 'running' => 1, 'failed' => 0, 'pending' => 0]

// Collect when done
if ($orchestrator->isComplete()) {
    $results = $orchestrator->results();
    echo "Total cost: $" . $results->totalCost();
}

// Or run synchronously (blocking)
$results = MbcOrchestrator::create('quick-task')
    ->agent($agentA, 'Task A')
    ->agent($agentB, 'Task B')
    ->runSync();
```

### Sub-Agents (Spawn from within a Session)

An agent can spawn specialized sub-agents during execution using the built-in `SpawnAgentTool`.

```php
use Undergrace\Mbc\Tools\SpawnAgentTool;

$spawnTool = new SpawnAgentTool();
$spawnTool
    ->register(
        name: 'researcher',
        systemPrompt: 'You research and gather information.',
        toolClasses: [WebSearchTool::class, ReadFileTool::class],
        maxTurns: 10,
    )
    ->register(
        name: 'writer',
        systemPrompt: 'You write content based on research.',
        toolClasses: [WriteFileTool::class],
        maxTurns: 15,
    );

$session = Mbc::session('coordinator')
    ->systemPrompt('You coordinate research and writing tasks. Use spawn_agent to delegate.')
    ->tools([
        $spawnTool,
        OtherTool::class,
    ])
    ->start('Research and write an article about Laravel.');
```

### Shared Context (Memory between Agents)

Agents can share data through a key-value store backed by Laravel's cache system.

```php
use Undergrace\Mbc\Core\MbcContext;

// In Agent A's tool
$ctx = new MbcContext('project-123');
$ctx->set('schema', $databaseSchema);
$ctx->push('decisions', 'Use PostgreSQL for main DB');

// In Agent B's tool (same namespace)
$ctx = new MbcContext('project-123');
$schema = $ctx->get('schema');
$decisions = $ctx->get('decisions'); // ['Use PostgreSQL for main DB']

// Get everything
$all = $ctx->all();

// Clean up when done
$ctx->flush();
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

The job includes automatic retry logic (3 attempts with 10s, 30s, 60s backoff) and zombie session cleanup on failure.

## Middleware

MBC includes built-in middleware and supports custom middleware:

```php
use Undergrace\Mbc\Middleware\LogTurns;
use Undergrace\Mbc\Middleware\CostTracker;
use Undergrace\Mbc\Middleware\RateLimiter;

$session = Mbc::session('with-middleware')
    ->middleware([
        LogTurns::class,
        CostTracker::class,
        RateLimiter::max(30),
    ])
    ->start('...');
```

| Middleware | Description |
|---|---|
| `LogTurns` | Logs each turn's response metadata and optionally full text |
| `CostTracker` | Tracks cumulative token usage and estimated cost per session |
| `RateLimiter` | Throws exception if session exceeds max turns |
| `VisualFeedback` | Captures screenshots for visual feedback loops |

Global middleware can be configured in `config/mbc.php`:

```php
'middleware' => [
    LogTurns::class,
    CostTracker::class,
],
```

## Scalability Features

### Context Window Management

Sessions automatically trim old messages when approaching the context window limit, preserving the initial context and most recent turns:

```php
$session = Mbc::session('long-task')
    ->config(
        maxTurns: 50,
        // Context window settings (defaults shown)
        // contextWindowLimit: 150000,
        // contextReserveTokens: 20000,
    )
    ->start('...');
```

### Concurrency Guard

Prevents overloading the system with too many simultaneous sessions:

```php
// config/mbc.php
'limits' => [
    'max_concurrent_sessions' => 10,
],
```

### Zombie Session Cleanup

Sessions stuck in RUNNING state are automatically handled:

```bash
# Mark sessions stuck for 60+ minutes as FAILED
php artisan mbc:cleanup

# Custom timeout
php artisan mbc:cleanup --timeout=30

# Also prune old sessions
php artisan mbc:cleanup --prune
```

Schedule it in your `routes/console.php` or kernel:

```php
Schedule::command('mbc:cleanup --prune')->hourly();
```

### Dynamic Cost Tracking

Costs are estimated per model using `ModelPricing`, supporting all providers:

```php
use Undergrace\Mbc\Core\ModelPricing;

$cost = ModelPricing::estimate('claude-sonnet-4-5-20250929', inputTokens: 50000, outputTokens: 10000);
// $0.30

$cost = ModelPricing::estimate('google/gemini-2.5-flash', inputTokens: 50000, outputTokens: 10000);
// $0.01
```

## Persistence

All sessions and turns are stored in the database by default:

- **`mbc_sessions`** — Session metadata, status, cost, result
- **`mbc_turns`** — Each turn's content, tool calls, tool results, tokens

Configure in `config/mbc.php`:

```php
'storage' => [
    'persist_sessions' => true,
    'persist_turns' => true,
    'prune_after_days' => 30,
],
```

## Artisan Commands

```bash
# Generate a new tool
php artisan mbc:make-tool {ToolName}

# Check session status
php artisan mbc:session-status {uuid}

# Replay a previous session
php artisan mbc:replay {uuid}

# Cleanup zombie sessions and prune old data
php artisan mbc:cleanup [--timeout=60] [--prune]
```

## Logging

MBC auto-registers its own log channel. Logs are written to `storage/logs/mbc.log`:

```php
// config/mbc.php
'logging' => [
    'channel' => 'mbc',
    'log_prompts' => env('MBC_LOG_PROMPTS', false),
    'log_responses' => env('MBC_LOG_RESPONSES', false),
],
```

## License

MIT - UNDERGRACE LABS
