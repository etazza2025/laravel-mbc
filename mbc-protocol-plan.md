# MBC — Model Backend Controller

## Protocolo de orquestación de agentes IA desde el backend

**Autor:** UNDERGRACE LABS
**Versión:** 0.1.0 (Diseño)
**Stack:** Laravel 12 + Anthropic API

---

## 1. ¿Qué es MBC?

MBC (Model Backend Controller) es un protocolo/framework que permite a un backend Laravel orquestar agentes de IA como trabajadores autónomos, dándoles herramientas para explorar datos, tomar decisiones y ejecutar acciones — todo server-side, sin necesidad de un cliente de escritorio.

### MBC vs MCP

| Aspecto | MCP (Anthropic) | MBC (UNDERGRACE) |
|---------|-----------------|-------------------|
| Dirección | IA → Backend | Backend → IA |
| Quién inicia | El usuario desde un IDE/Desktop | Tu aplicación Laravel |
| Quién orquesta | El cliente MCP | Tu servidor Laravel |
| Dónde corre | Requiere app de escritorio | 100% server-side |
| Estado | Sesión del cliente | Sesión persistida en DB |
| Visual feedback | No | Sí (render → vision → ajuste) |
| Caso de uso | Humano usa IA que llama APIs | Backend usa IA como agente |

### Concepto clave

MBC convierte a la IA en un **agente controlado por el backend** que puede:

1. **Explorar** — Pedir datos bajo demanda (no recibir todo de golpe)
2. **Decidir** — Analizar lo que exploró y planificar acciones
3. **Ejecutar** — Llamar herramientas para crear/modificar recursos
4. **Verificar** — Ver el resultado visual y corregir si es necesario
5. **Iterar** — Repetir el ciclo hasta lograr calidad profesional

---

## 2. Arquitectura General

```
┌─────────────────────────────────────────────────────────┐
│                    Tu Aplicación Laravel                 │
│                                                         │
│  ┌──────────┐    ┌──────────────┐    ┌───────────────┐  │
│  │  Wizard   │───▶│ MbcSession   │───▶│ MbcAgent      │  │
│  │  (User)   │    │ (Orchestrator)│   │ (Tool Runner) │  │
│  └──────────┘    └──────┬───────┘    └───────┬───────┘  │
│                         │                     │          │
│                         ▼                     ▼          │
│                  ┌──────────────┐    ┌───────────────┐   │
│                  │ MbcProvider  │    │ MbcToolkit    │   │
│                  │ (AI Client)  │    │ (Tool Registry)│  │
│                  └──────┬───────┘    └───────────────┘   │
│                         │                                │
│                         ▼                                │
│              ┌─────────────────────┐                     │
│              │  Anthropic API      │                     │
│              │  (Claude Sonnet 4)  │                     │
│              └─────────────────────┘                     │
│                                                         │
│  ┌─────────────────────────────────────────────────┐    │
│  │  MbcVisualFeedback (opcional)                    │    │
│  │  Browsershot → Screenshot → Vision API → Ajuste  │    │
│  └─────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────┘
```

---

## 3. Stack Tecnológico

### Core del protocolo

| Componente | Tecnología | Propósito |
|-----------|-----------|-----------|
| Runtime | PHP 8.3+ / Laravel 12 | Orquestación y ejecución |
| AI Provider | Anthropic API (Claude Sonnet 4.5) | Motor de razonamiento |
| HTTP Client | Laravel HTTP (Guzzle) | Comunicación con API |
| Queue | Laravel Horizon + Redis | Sesiones async pesadas |
| Storage | MySQL 8 | Persistencia de sesiones, turnos, logs |
| Cache | Redis | Caché de schemas, tokens, resultados parciales |
| Logging | Laravel Log + canal dedicado | Debug y auditoría de turnos |

### Visual Feedback (opcional)

| Componente | Tecnología | Propósito |
|-----------|-----------|-----------|
| Renderer | Browsershot (Puppeteer) | Captura screenshot del resultado |
| Vision | Anthropic API (vision) | Claude analiza la imagen |
| Queue | Job dedicado | El render es async |

### Para el SaaS Builder (caso de uso)

| Componente | Tecnología | Propósito |
|-----------|-----------|-----------|
| Component Catalog | DB MySQL | Schemas reales de componentes |
| Template Engine | Blade / Vue SSR | Renderizado de preview |
| Asset Pipeline | Vite + Tailwind | Estilos del sitio generado |

---

## 4. Estructura del Package

```
packages/undergrace/laravel-mbc/
├── src/
│   ├── MbcServiceProvider.php          # Service provider del package
│   ├── Facades/
│   │   └── Mbc.php                     # Facade principal
│   │
│   ├── Contracts/                      # Interfaces del protocolo
│   │   ├── MbcProviderInterface.php    # Contrato para AI providers
│   │   ├── MbcToolInterface.php        # Contrato para tools
│   │   ├── MbcMiddlewareInterface.php  # Contrato para middleware
│   │   └── MbcRendererInterface.php    # Contrato para visual feedback
│   │
│   ├── Core/
│   │   ├── MbcSession.php              # Sesión: orquesta todo el flujo
│   │   ├── MbcAgent.php                # Agente: ejecuta tools localmente
│   │   ├── MbcToolkit.php              # Registro y resolución de tools
│   │   ├── MbcTurn.php                 # Un turno de conversación
│   │   └── MbcResult.php              # Resultado final tipado
│   │
│   ├── Providers/
│   │   ├── AnthropicProvider.php       # Implementación Claude
│   │   └── OpenAIProvider.php          # Implementación OpenAI (futuro)
│   │
│   ├── Tools/
│   │   ├── BaseTool.php                # Clase base para tools
│   │   └── Attributes/
│   │       ├── Tool.php                # #[Tool] attribute
│   │       └── ToolParam.php           # #[ToolParam] attribute
│   │
│   ├── Middleware/
│   │   ├── LogTurns.php                # Log de cada turno
│   │   ├── CostTracker.php             # Tracking de tokens/costo
│   │   ├── RateLimiter.php             # Límite de turnos por sesión
│   │   └── VisualFeedback.php          # Inyecta render visual
│   │
│   ├── Events/
│   │   ├── MbcSessionStarted.php
│   │   ├── MbcTurnCompleted.php
│   │   ├── MbcToolExecuted.php
│   │   ├── MbcSessionCompleted.php
│   │   └── MbcSessionFailed.php
│   │
│   ├── Jobs/
│   │   └── RunMbcSessionJob.php        # Ejecutar sesión en background
│   │
│   ├── Models/
│   │   ├── MbcSession.php              # Modelo Eloquent
│   │   └── MbcTurn.php                 # Modelo Eloquent
│   │
│   ├── DTOs/
│   │   ├── MbcConfig.php               # Configuración de sesión
│   │   ├── ToolDefinition.php          # Definición de tool para API
│   │   ├── ToolCall.php                # Llamada a tool desde la IA
│   │   ├── ToolResult.php              # Resultado de ejecución
│   │   └── SessionResult.php           # Resultado final
│   │
│   └── Enums/
│       ├── SessionStatus.php           # pending, running, completed, failed
│       ├── TurnType.php                # assistant, tool_use, tool_result
│       └── StopReason.php              # end_turn, tool_use, max_tokens
│
├── config/
│   └── mbc.php                         # Configuración publicable
│
├── database/
│   └── migrations/
│       ├── create_mbc_sessions_table.php
│       └── create_mbc_turns_table.php
│
├── tests/
│   ├── Unit/
│   │   ├── MbcSessionTest.php
│   │   ├── MbcAgentTest.php
│   │   └── MbcToolkitTest.php
│   └── Feature/
│       └── MbcFullFlowTest.php
│
├── composer.json
└── README.md
```

---

## 5. Diseño del Protocolo

### 5.1 Definir Tools con PHP Attributes

```php
use Undergrace\Mbc\Tools\BaseTool;
use Undergrace\Mbc\Tools\Attributes\Tool;
use Undergrace\Mbc\Tools\Attributes\ToolParam;

#[Tool(
    name: 'list_components',
    description: 'Lista todas las categorías de componentes disponibles con sus nombres y descripción'
)]
class ListComponentsTool extends BaseTool
{
    public function __construct(
        private ComponentRepository $componentRepo
    ) {}

    public function execute(array $input): mixed
    {
        return $this->componentRepo->getCategories()
            ->map(fn ($cat) => [
                'id' => $cat->slug,
                'name' => $cat->name,
                'components' => $cat->components->pluck('name', 'slug'),
            ]);
    }
}

#[Tool(
    name: 'get_component_schema',
    description: 'Obtiene el schema completo de un componente: props, slots, estilos configurables, variantes disponibles'
)]
class GetComponentSchemaTool extends BaseTool
{
    public function __construct(
        private ComponentRepository $componentRepo
    ) {}

    #[ToolParam(name: 'component_slug', type: 'string', description: 'Slug del componente', required: true)]
    public function execute(array $input): mixed
    {
        $component = $this->componentRepo->findBySlug($input['component_slug']);

        return [
            'slug' => $component->slug,
            'name' => $component->name,
            'category' => $component->category->name,
            'props' => $component->props_schema,       // JSON Schema de props
            'slots' => $component->slots_schema,        // Slots disponibles
            'styles' => $component->style_options,      // Estilos configurables
            'variants' => $component->variants,         // Variantes predefinidas
            'preview_data' => $component->sample_data,  // Datos de ejemplo
        ];
    }
}

#[Tool(
    name: 'assemble_site',
    description: 'Crea el sitio final con todas las páginas y secciones. Llamar solo cuando toda la estructura esté definida.'
)]
class AssembleSiteTool extends BaseTool
{
    public function __construct(
        private SiteBuilderService $builder
    ) {}

    #[ToolParam(name: 'site_name', type: 'string', description: 'Nombre del sitio')]
    #[ToolParam(name: 'pages', type: 'array', description: 'Array de páginas con sus secciones y configuración')]
    #[ToolParam(name: 'theme', type: 'object', description: 'Configuración del tema: colores, tipografía, spacing')]
    public function execute(array $input): mixed
    {
        $result = $this->builder->assemble($input);

        return [
            'site_id' => $result->site->id,
            'pages_created' => $result->pages->count(),
            'preview_url' => $result->previewUrl,
        ];
    }
}
```

### 5.2 Crear una Sesión MBC

```php
use Undergrace\Mbc\Facades\Mbc;

class AiBuilderService
{
    public function buildFromWizard(WizardDTO $wizard): SessionResult
    {
        $session = Mbc::session('ai-site-builder')
            // System prompt: el "cerebro" del agente
            ->systemPrompt($this->buildDesignerPrompt($wizard))

            // Herramientas disponibles
            ->tools([
                ListComponentsTool::class,
                GetComponentSchemaTool::class,
                GetDesignTokensTool::class,
                AssembleSiteTool::class,
            ])

            // Contexto inicial (lo que viene del wizard)
            ->context([
                'business_type' => $wizard->businessType,
                'business_name' => $wizard->businessName,
                'target_audience' => $wizard->targetAudience,
                'style_preference' => $wizard->stylePreference,
                'pages_requested' => $wizard->pages,
                'color_preference' => $wizard->colorPreference,
            ])

            // Configuración del agente
            ->config(
                maxTurns: 30,            // Máximo de turnos
                maxTokensPerTurn: 4096,  // Tokens por respuesta
                model: 'claude-sonnet-4-5-20250929',
                temperature: 0.7,
            )

            // Middleware
            ->middleware([
                LogTurns::class,
                CostTracker::class,
                RateLimiter::max(50),
            ])

            // Mensaje inicial que arranca al agente
            ->start("Crea un sitio web profesional para este negocio. 
                     Explora los componentes disponibles, selecciona los más 
                     apropiados, y arma un sitio que impresione.");

        return $session->result();
    }
}
```

### 5.3 El Loop Multi-turno (Core del protocolo)

```php
// Dentro de MbcSession — el corazón de MBC
class MbcSession
{
    public function start(string $initialMessage): self
    {
        $this->messages = [
            ['role' => 'user', 'content' => $this->buildInitialMessage($initialMessage)],
        ];

        $this->status = SessionStatus::RUNNING;
        $turnCount = 0;

        while ($turnCount < $this->config->maxTurns) {
            $turnCount++;

            // 1. Llamar al AI Provider
            $response = $this->provider->complete(
                system: $this->systemPrompt,
                messages: $this->messages,
                tools: $this->toolkit->definitions(),
                maxTokens: $this->config->maxTokensPerTurn,
                model: $this->config->model,
            );

            // 2. Ejecutar middleware pre-procesamiento
            $response = $this->runMiddleware('afterResponse', $response);

            // 3. Guardar turno del asistente
            $this->addTurn(TurnType::ASSISTANT, $response);

            // 4. Si la IA terminó → salir del loop
            if ($response->stopReason === StopReason::END_TURN) {
                $this->status = SessionStatus::COMPLETED;
                break;
            }

            // 5. Si la IA quiere usar tools → ejecutar localmente
            if ($response->stopReason === StopReason::TOOL_USE) {
                $toolResults = $this->agent->executeTools($response->toolCalls);

                // 6. Ejecutar middleware post-tool
                $toolResults = $this->runMiddleware('afterToolExecution', $toolResults);

                // 7. Agregar resultados como mensaje del "user"
                $this->addToolResults($toolResults);
            }
        }

        if ($turnCount >= $this->config->maxTurns) {
            $this->status = SessionStatus::MAX_TURNS_REACHED;
        }

        $this->persist();

        return $this;
    }
}
```

### 5.4 Visual Feedback Loop (Opcional)

```php
// Middleware que inyecta verificación visual
class VisualFeedback implements MbcMiddlewareInterface
{
    public function __construct(
        private MbcRendererInterface $renderer
    ) {}

    public function afterToolExecution(array $toolResults): array
    {
        // Solo activar después de assemble_site
        $assembleResult = collect($toolResults)
            ->firstWhere('tool_name', 'assemble_site');

        if (!$assembleResult) {
            return $toolResults;
        }

        // Generar screenshot del sitio creado
        $screenshot = $this->renderer->capture(
            url: $assembleResult->result['preview_url'],
            viewports: ['desktop', 'mobile'],
        );

        // Inyectar las imágenes como contenido adicional
        $toolResults[] = new ToolResult(
            toolName: '_visual_feedback',
            content: [
                [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => 'image/png',
                        'data' => $screenshot->desktop,
                    ],
                ],
                [
                    'type' => 'text',
                    'text' => 'Revisa el resultado visual. Si hay problemas de diseño '
                            . '(contraste, spacing, jerarquía, balance), usa update_component '
                            . 'para corregir. Si todo se ve profesional, termina.',
                ],
            ],
        );

        return $toolResults;
    }
}
```

---

## 6. Base de Datos

### Tabla: mbc_sessions

```
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
uuid                CHAR(36) UNIQUE
name                VARCHAR(255)         -- Nombre descriptivo
status              ENUM(pending, running, completed, failed, max_turns)
model               VARCHAR(100)         -- claude-sonnet-4-5-20250929
system_prompt       LONGTEXT
context             JSON                 -- Datos iniciales
config              JSON                 -- maxTurns, temperature, etc.
total_turns         INT DEFAULT 0
total_input_tokens  INT DEFAULT 0
total_output_tokens INT DEFAULT 0
estimated_cost_usd  DECIMAL(10,6)
result              JSON                 -- Resultado final
error               TEXT NULL
started_at          TIMESTAMP NULL
completed_at        TIMESTAMP NULL
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

### Tabla: mbc_turns

```
id                  BIGINT UNSIGNED PK AUTO_INCREMENT
session_id          BIGINT UNSIGNED FK → mbc_sessions
turn_number         INT
type                ENUM(user, assistant, tool_use, tool_result)
content             JSON                 -- Contenido del mensaje
tool_calls          JSON NULL            -- Tools que la IA pidió
tool_results        JSON NULL            -- Resultados de tools
input_tokens        INT NULL
output_tokens       INT NULL
stop_reason         VARCHAR(50) NULL
duration_ms         INT NULL             -- Tiempo de ejecución
created_at          TIMESTAMP
```

---

## 7. Configuración (config/mbc.php)

```php
return [
    // Provider por defecto
    'default_provider' => 'anthropic',

    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => 'https://api.anthropic.com/v1',
            'default_model' => 'claude-sonnet-4-5-20250929',
            'timeout' => 120,
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],
    ],

    // Límites globales
    'limits' => [
        'max_turns_per_session' => 50,
        'max_tokens_per_turn' => 8192,
        'max_concurrent_sessions' => 10,
        'session_timeout_minutes' => 30,
    ],

    // Visual feedback
    'visual_feedback' => [
        'enabled' => false,
        'renderer' => 'browsershot', // browsershot | puppeteer
        'viewports' => [
            'desktop' => ['width' => 1440, 'height' => 900],
            'mobile' => ['width' => 375, 'height' => 812],
        ],
    ],

    // Persistencia
    'storage' => [
        'persist_sessions' => true,
        'persist_turns' => true,
        'prune_after_days' => 30,
    ],

    // Middleware global
    'middleware' => [
        LogTurns::class,
        CostTracker::class,
    ],

    // Logging
    'logging' => [
        'channel' => 'mbc',
        'log_prompts' => env('MBC_LOG_PROMPTS', false),
        'log_responses' => env('MBC_LOG_RESPONSES', false),
    ],
];
```

---

## 8. Fases de Desarrollo

### Fase 1: Core Protocol (Semana 1-2)
**Objetivo:** Loop multi-turno funcional

- [ ] DTOs base: MbcConfig, ToolDefinition, ToolCall, ToolResult
- [ ] Enums: SessionStatus, TurnType, StopReason
- [ ] MbcToolkit: registro y resolución de tools
- [ ] BaseTool + PHP Attributes (#[Tool], #[ToolParam])
- [ ] AnthropicProvider: wrapper del API con tool-use
- [ ] MbcAgent: ejecutor local de tools
- [ ] MbcSession: loop multi-turno completo
- [ ] Tests unitarios del loop

### Fase 2: Persistencia y Jobs (Semana 2-3)
**Objetivo:** Sesiones persistidas y ejecución async

- [ ] Migraciones: mbc_sessions, mbc_turns
- [ ] Modelos Eloquent con relaciones
- [ ] Persistencia automática de cada turno
- [ ] RunMbcSessionJob para ejecución en background
- [ ] Events: SessionStarted, TurnCompleted, ToolExecuted, etc.
- [ ] MbcServiceProvider y Facade
- [ ] Config publicable

### Fase 3: Middleware y Visual Feedback (Semana 3-4)
**Objetivo:** Pipeline extensible + verificación visual

- [ ] Pipeline de middleware (pre/post response, pre/post tool)
- [ ] LogTurns middleware
- [ ] CostTracker middleware
- [ ] RateLimiter middleware
- [ ] VisualFeedback middleware con Browsershot
- [ ] MbcRendererInterface + BrowsershotRenderer
- [ ] Inyección de screenshots como contenido visual

### Fase 4: DX y Producción (Semana 4-5)
**Objetivo:** Developer experience y robustez

- [ ] Comando artisan: `mbc:make-tool {name}`
- [ ] Comando artisan: `mbc:session-status {uuid}`
- [ ] Comando artisan: `mbc:replay {uuid}` (re-ejecutar sesión)
- [ ] Dashboard simple (Pulse integration o propio)
- [ ] Retry automático en errores de API
- [ ] Timeout handling por turno
- [ ] Pruning de sesiones antiguas
- [ ] Tests de integración completos
- [ ] README y documentación

### Fase 5: Caso de uso — AI Site Builder (Semana 5-7)
**Objetivo:** Implementar el builder usando MBC

- [ ] Tools específicos: ListComponents, GetSchema, GetDesignTokens, AssembleSite, UpdateComponent
- [ ] System prompt de diseñador UI experto
- [ ] Integración con el Wizard existente
- [ ] Visual feedback loop con preview del sitio
- [ ] Testing E2E del flujo completo
- [ ] Optimización de prompts y calidad del output

---

## 9. Ejemplo de Uso Final

```php
// En un Controller o Service de tu SaaS
class WizardController extends Controller
{
    public function __construct(
        private AiBuilderService $aiBuilder
    ) {}

    public function generate(WizardRequest $request)
    {
        $wizard = WizardDTO::fromRequest($request);

        // Despachar en background
        $sessionId = $this->aiBuilder->buildAsync($wizard);

        return response()->json([
            'session_id' => $sessionId,
            'status_url' => route('api.mbc.status', $sessionId),
        ]);
    }
}

// El service usa MBC
class AiBuilderService
{
    public function buildAsync(WizardDTO $wizard): string
    {
        $session = Mbc::session('ai-site-builder')
            ->systemPrompt($this->getDesignerPrompt($wizard))
            ->tools([
                ListComponentsTool::class,
                GetComponentSchemaTool::class,
                GetDesignTokensTool::class,
                AssembleSiteTool::class,
                UpdateComponentTool::class,
            ])
            ->context($wizard->toArray())
            ->config(maxTurns: 30, model: 'claude-sonnet-4-5-20250929')
            ->middleware([
                LogTurns::class,
                CostTracker::class,
                VisualFeedback::class,
            ]);

        // Ejecutar en cola con Horizon
        RunMbcSessionJob::dispatch($session, 
            "Diseña un sitio web profesional para este negocio."
        );

        return $session->uuid;
    }
}
```

---

## 10. Dependencias del Package

```json
{
    "name": "undergrace/laravel-mbc",
    "description": "Model Backend Controller - AI agent orchestration for Laravel",
    "require": {
        "php": "^8.3",
        "laravel/framework": "^12.0",
        "guzzlehttp/guzzle": "^7.0",
        "ramsey/uuid": "^4.0"
    },
    "require-dev": {
        "orchestra/testbench": "^10.0",
        "pestphp/pest": "^3.0",
        "mockery/mockery": "^1.6"
    },
    "suggest": {
        "spatie/browsershot": "Required for visual feedback feature",
        "laravel/horizon": "Recommended for background session processing"
    }
}
```

---

## 11. Métricas Clave a Trackear

| Métrica | Propósito |
|---------|-----------|
| Turnos por sesión | Eficiencia del agente |
| Tokens totales (input/output) | Costo |
| Costo USD por sesión | Presupuesto |
| Tiempo total de sesión | Performance |
| Tools más usados | Optimización |
| Tasa de éxito/fallo | Reliability |
| Turnos de visual feedback | Calidad del output |

---

## 12. Roadmap Futuro

- **v0.2** — Soporte para OpenAI y otros providers
- **v0.3** — Streaming de turnos via WebSocket (Reverb) para UI en tiempo real
- **v0.4** — Sub-agentes: un agente puede crear otro agente para subtareas
- **v0.5** — MBC Hub: marketplace de tools compartidos entre proyectos
- **v1.0** — Package open-source publicado en Packagist
