# Creator AI - Repository Analysis Report

## Indice

1. [Panoramica Generale](#panoramica-generale)
2. [Architettura del Sistema](#architettura-del-sistema)
3. [Struttura delle Directory](#struttura-delle-directory)
4. [Firebase Cloud Functions (Backend)](#firebase-cloud-functions-backend)
5. [WordPress Plugin (Frontend)](#wordpress-plugin-frontend)
6. [Flusso dei Dati e Interazioni](#flusso-dei-dati-e-interazioni)
7. [Diagrammi di Architettura](#diagrammi-di-architettura)

---

## Panoramica Generale

**Creator AI** è un assistente di sviluppo WordPress alimentato da intelligenza artificiale. Il progetto è strutturato come **monorepo** contenente due componenti principali:

1. **Firebase Cloud Functions** - Backend serverless in TypeScript che gestisce routing AI, autenticazione e task processing
2. **WordPress Plugin** - Plugin PHP che fornisce l'interfaccia utente e l'esecuzione delle azioni WordPress

### Tecnologie Principali

| Componente | Tecnologia | Versione |
|------------|------------|----------|
| Backend | Firebase Cloud Functions | Node.js 20 |
| Frontend | WordPress Plugin | PHP 7.4+ |
| AI Providers | Claude, Gemini, OpenAI | Multi-provider |
| Database | Firestore | - |
| Autenticazione | JWT | jsonwebtoken 9.x |

---

## Architettura del Sistema

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        WORDPRESS SITE                                    │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │                    Creator Core Plugin                            │   │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐   │   │
│  │  │ Chat         │  │ Action       │  │ Context              │   │   │
│  │  │ Interface    │──│ Dispatcher   │──│ Collector            │   │   │
│  │  └──────────────┘  └──────────────┘  └──────────────────────┘   │   │
│  │         │                  │                    │                │   │
│  │         ▼                  ▼                    ▼                │   │
│  │  ┌──────────────────────────────────────────────────────────┐   │   │
│  │  │                     ProxyClient                           │   │   │
│  │  │            (HTTP Communication Layer)                     │   │   │
│  │  └──────────────────────────────────────────────────────────┘   │   │
│  └─────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ HTTPS (JWT Auth)
                                    ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                    FIREBASE CLOUD FUNCTIONS                              │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                        API Layer                                  │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐ │   │
│  │  │ validate   │  │ route      │  │ submit     │  │ get        │ │   │
│  │  │ License    │  │ Request    │  │ Task       │  │ Analytics  │ │   │
│  │  └────────────┘  └────────────┘  └────────────┘  └────────────┘ │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                    │                                     │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                      Service Layer                                │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐ │   │
│  │  │ AIRouter   │  │ Licensing  │  │ Cost       │  │ Job        │ │   │
│  │  │            │  │ Service    │  │ Calculator │  │ Processor  │ │   │
│  │  └────────────┘  └────────────┘  └────────────┘  └────────────┘ │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                    │                                     │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                     Provider Layer                                │   │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐                  │   │
│  │  │ Claude     │  │ Gemini     │  │ OpenAI     │                  │   │
│  │  │ Provider   │  │ Provider   │  │ Provider   │                  │   │
│  │  └────────────┘  └────────────┘  └────────────┘                  │   │
│  └──────────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
                    ┌───────────────────────────┐
                    │      FIRESTORE DB         │
                    │  ┌─────────┐ ┌─────────┐  │
                    │  │licenses │ │ jobs    │  │
                    │  └─────────┘ └─────────┘  │
                    │  ┌─────────┐ ┌─────────┐  │
                    │  │usage    │ │ audits  │  │
                    │  └─────────┘ └─────────┘  │
                    └───────────────────────────┘
```

---

## Struttura delle Directory

```
creator/
├── functions/                      # Firebase Cloud Functions (Backend)
│   ├── src/
│   │   ├── api/                    # Endpoint HTTP
│   │   │   ├── ai/                 # Routing AI
│   │   │   ├── analytics/          # Analytics
│   │   │   ├── auth/               # Autenticazione
│   │   │   ├── plugin-docs/        # Documentazione plugin
│   │   │   └── tasks/              # Task asincroni
│   │   ├── config/                 # Configurazioni
│   │   ├── lib/                    # Librerie condivise
│   │   ├── middleware/             # Middleware (auth, rate limit)
│   │   ├── providers/              # Provider AI
│   │   ├── scripts/                # Script utility
│   │   ├── services/               # Servizi business logic
│   │   ├── triggers/               # Trigger Firestore
│   │   └── types/                  # TypeScript types
│   ├── package.json
│   └── tsconfig.json
│
├── packages/
│   └── creator-core-plugin/        # WordPress Plugin
│       └── creator-core/
│           ├── assets/
│           │   ├── css/            # Stili
│           │   └── js/             # JavaScript frontend
│           ├── database/           # Migrazioni DB
│           ├── includes/
│           │   ├── Admin/          # Pagine admin
│           │   ├── API/            # REST API WordPress
│           │   ├── Audit/          # Logging audit
│           │   ├── Backup/         # Sistema backup/rollback
│           │   ├── Chat/           # Interfaccia chat
│           │   ├── Context/        # Raccolta contesto
│           │   ├── Development/    # Strumenti sviluppo
│           │   ├── Executor/       # Esecuzione codice
│           │   ├── Integrations/   # Integrazioni plugin
│           │   ├── Permission/     # Gestione permessi
│           │   └── User/           # Profilo utente
│           ├── templates/          # Template PHP
│           └── tests/              # Test PHPUnit
│
├── firebase.json                   # Configurazione Firebase
└── package.json                    # Root package.json
```

---

## Firebase Cloud Functions (Backend)

### Entry Point

#### `functions/src/index.ts`
**Scopo**: Entry point principale che esporta tutte le Cloud Functions.

**Funzioni esportate**:
- `validateLicense` - Validazione licenze (europe-west1)
- `routeRequest` - Routing richieste AI (us-central1)
- `submitTask` - Sottomissione task asincroni
- `getTaskStatus` - Stato task
- `getAnalytics` - Analytics
- `getPluginDocsDoc` / `searchPluginDocs` - Documentazione plugin
- `processJobQueue` - Trigger Firestore per job queue

**Interazioni**: Importa e re-esporta da tutti i moduli API e triggers.

---

### API Layer

#### `functions/src/api/auth/validateLicense.ts`
**Scopo**: Validazione licenze e generazione token JWT per i siti WordPress.

**Funzionalità**:
- Verifica chiave licenza in Firestore
- Controlla stato attivazione e scadenza
- Genera JWT token per autenticazione successiva
- Traccia utilizzo licenza

**Interazioni**:
- → `services/licensing.ts` (validazione)
- → `lib/jwt.ts` (generazione token)
- → Firestore collection `licenses`

---

#### `functions/src/api/ai/routeRequest.ts`
**Scopo**: Endpoint principale per routing delle richieste AI.

**Funzionalità**:
- Autenticazione JWT
- Rate limiting (100 req/min)
- Verifica quota utente
- Routing a ModelService
- Tracking costi e audit logging

**Configurazione**:
```typescript
{
  secrets: [jwtSecret, geminiApiKey, claudeApiKey],
  cors: true,
  maxInstances: 100,
  timeoutSeconds: 120
}
```

**Interazioni**:
- → `middleware/auth.ts` (autenticazione)
- → `middleware/rateLimit.ts` (rate limiting)
- → `services/modelService.ts` (generazione AI)
- → `services/costCalculator.ts` (calcolo costi)
- → Firestore collections `usage`, `audits`

---

#### `functions/src/api/tasks/submitTask.ts`
**Scopo**: Sottomissione task asincroni per elaborazione batch.

**Funzionalità**:
- Validazione payload task
- Creazione job in Firestore
- Supporto tipi: `bulk_article`, `design_batch`, `bulk_product`

**Interazioni**:
- → Firestore collection `jobs`
- → `types/Job.ts` (definizione tipi)

---

#### `functions/src/api/tasks/getStatus.ts`
**Scopo**: Recupero stato task asincroni.

**Funzionalità**:
- Query job per ID
- Restituisce stato, progresso, risultati

**Interazioni**:
- → Firestore collection `jobs`

---

#### `functions/src/api/analytics/getAnalytics.ts`
**Scopo**: Endpoint analytics per dashboard admin.

**Funzionalità**:
- Aggregazione dati utilizzo
- Statistiche per provider/modello
- Costi totali

**Interazioni**:
- → Firestore collections `usage`, `audits`

---

#### `functions/src/api/plugin-docs/pluginDocs.ts`
**Scopo**: Ricerca e recupero documentazione plugin WordPress.

**Funzionalità**:
- `getPluginDocsDoc` - Recupera documento specifico
- `searchPluginDocs` - Ricerca full-text

**Interazioni**:
- → `services/pluginDocsResearch.ts`
- → Firestore collection `plugin_docs`

---

### Service Layer

#### `functions/src/services/aiRouter.ts`
**Scopo**: Router intelligente per selezione provider AI ottimale.

**Classe**: `AIRouter`

**Funzionalità**:
- Routing basato su task type (CODE_GEN, TEXT_GEN, ANALYSIS, etc.)
- Fallback automatico: primary → fallback1 → fallback2
- Caching provider instances
- Sanitizzazione e validazione prompt

**Metodi principali**:
```typescript
route(taskType: TaskType, prompt: string, options?: GenerateOptions): Promise<AIRouterResult>
getRouteConfig(taskType: TaskType): ProviderRouteConfig[]
```

**Interazioni**:
- → `providers/claude.ts`, `providers/gemini.ts`, `providers/openai.ts`
- → `types/Route.ts` (routing matrix)

---

#### `functions/src/services/modelService.ts`
**Scopo**: Servizio di alto livello per generazione AI.

**Funzionalità**:
- Wrappa AIRouter con logging aggiuntivo
- Gestione errori centralizzata
- Metriche di performance

**Interazioni**:
- → `services/aiRouter.ts`
- → `lib/logger.ts`

---

#### `functions/src/services/licensing.ts`
**Scopo**: Gestione licenze e quote.

**Funzionalità**:
- Validazione licenze
- Tracking utilizzo
- Verifica quote
- Gestione scadenze

**Interazioni**:
- → Firestore collection `licenses`
- → `types/License.ts`

---

#### `functions/src/services/costCalculator.ts`
**Scopo**: Calcolo costi per richieste AI.

**Funzionalità**:
- Pricing per provider/modello
- Calcolo costi input/output token
- Aggregazione costi mensili

**Interazioni**:
- → `config/models.ts` (prezzi modelli)

---

#### `functions/src/services/jobProcessor.ts`
**Scopo**: Processore per job queue asincroni.

**Funzionalità**:
- Elaborazione job in background
- Retry con backoff esponenziale
- Aggiornamento stato job

**Interazioni**:
- → `services/taskProcessors/*`
- → Firestore collection `jobs`

---

#### `functions/src/services/pluginDocsResearch.ts`
**Scopo**: Ricerca documentazione plugin per contesto AI.

**Funzionalità**:
- Indicizzazione documentazione
- Ricerca semantica
- Estrazione snippet rilevanti

**Interazioni**:
- → Firestore collection `plugin_docs`

---

#### `functions/src/services/taskProcessors/`
**Scopo**: Processori specifici per tipi di task.

**File**:
- `bulkArticleProcessor.ts` - Generazione articoli in batch
- `bulkProductProcessor.ts` - Creazione prodotti WooCommerce
- `designBatchProcessor.ts` - Batch design Elementor
- `index.ts` - Export aggregato

---

### Provider Layer

#### `functions/src/providers/claude.ts`
**Scopo**: Provider per Anthropic Claude API.

**Classe**: `ClaudeProvider implements IAIProvider`

**Configurazione**:
- Modello default: `claude-opus-4-5-20251101`
- Max tokens: 16384
- Supporto multimodale (immagini)

**Funzionalità**:
- Generazione testo
- Retry con backoff esponenziale
- Counting token via tiktoken
- Supporto immagini base64

**Metodi principali**:
```typescript
generate(prompt: string, options?: GenerateOptions): Promise<AIProviderResponse>
buildMessageContent(prompt: string, images?: string[]): MessageParam
```

**Interazioni**:
- → `@anthropic-ai/sdk`
- → `types/AIProvider.ts`

---

#### `functions/src/providers/gemini.ts`
**Scopo**: Provider per Google Gemini API.

**Classe**: `GeminiProvider implements IAIProvider`

**Configurazione**:
- Modello default: `gemini-2.0-flash-thinking-exp-01-21`
- Supporto thinking/reasoning
- Safety settings configurabili

**Funzionalità**:
- Generazione con extended thinking
- Retry con backoff
- Supporto multimodale

**Interazioni**:
- → `@google/generative-ai`
- → `types/AIProvider.ts`

---

#### `functions/src/providers/openai.ts`
**Scopo**: Provider per OpenAI API.

**Classe**: `OpenAIProvider implements IAIProvider`

**Configurazione**:
- Modello default: `gpt-4o`
- Max tokens configurabile

**Funzionalità**:
- Chat completions
- Token counting
- Error handling

**Interazioni**:
- → `openai` SDK
- → `types/AIProvider.ts`

---

### Middleware

#### `functions/src/middleware/auth.ts`
**Scopo**: Middleware autenticazione JWT.

**Funzionalità**:
- Verifica token Bearer
- Estrazione claims
- Validazione scadenza

**Interazioni**:
- → `lib/jwt.ts`
- → `types/JWTClaims.ts`

---

#### `functions/src/middleware/rateLimit.ts`
**Scopo**: Rate limiting per protezione API.

**Configurazione**:
- 100 richieste/minuto per licenza (AI routes)
- Sliding window algorithm

**Interazioni**:
- → Firestore collection `rate_limits`

---

### Triggers

#### `functions/src/triggers/jobQueueTrigger.ts`
**Scopo**: Trigger Firestore per elaborazione job queue.

**Funzionalità**:
- Attivato su creazione documento in `jobs`
- Invoca jobProcessor
- Aggiorna stato job

**Interazioni**:
- → `services/jobProcessor.ts`
- → Firestore collection `jobs`

---

### Configuration

#### `functions/src/config/models.ts`
**Scopo**: Configurazione modelli AI e pricing.

**Contenuto**:
- Lista modelli supportati per provider
- Prezzi input/output per 1K token
- Limiti contesto

---

### Types

#### `functions/src/types/AIProvider.ts`
**Scopo**: Interfacce TypeScript per provider AI.

**Tipi principali**:
```typescript
interface IAIProvider {
  generate(prompt: string, options?: GenerateOptions): Promise<AIProviderResponse>
}

interface AIProviderResponse {
  success: boolean
  content: string
  provider: ProviderName
  model: string
  tokens_input: number
  tokens_output: number
  total_tokens: number
  cost_usd: number
  latency_ms: number
  error?: string
}
```

---

#### `functions/src/types/Route.ts`
**Scopo**: Definizione routing matrix per task types.

**Tipi principali**:
```typescript
type TaskType = 'CODE_GEN' | 'TEXT_GEN' | 'ANALYSIS' | 'TRANSLATION' | 'SUMMARY'

interface ProviderRouteConfig {
  provider: ProviderName
  model: string
}

interface RouteConfig {
  primary: ProviderRouteConfig
  fallback1: ProviderRouteConfig
  fallback2: ProviderRouteConfig
}
```

---

#### `functions/src/types/License.ts`
**Scopo**: Tipi per gestione licenze.

```typescript
interface License {
  license_key: string
  status: 'active' | 'expired' | 'suspended'
  site_url: string
  expires_at: Timestamp
  quota_limit: number
  quota_used: number
}
```

---

#### `functions/src/types/Job.ts`
**Scopo**: Tipi per job queue.

```typescript
interface Job {
  id: string
  type: 'bulk_article' | 'design_batch' | 'bulk_product'
  status: 'pending' | 'processing' | 'completed' | 'failed'
  payload: object
  result?: object
  progress: number
}
```

---

### Library

#### `functions/src/lib/jwt.ts`
**Scopo**: Utility JWT per sign/verify token.

**Funzioni**:
- `signToken(claims: JWTClaims): string`
- `verifyToken(token: string): JWTClaims`

**Interazioni**:
- → `jsonwebtoken` library
- → `lib/secrets.ts` (JWT secret)

---

#### `functions/src/lib/firestore.ts`
**Scopo**: Inizializzazione e helper Firestore.

**Funzionalità**:
- Singleton Firebase Admin
- Helper per query comuni
- Transazioni

---

#### `functions/src/lib/logger.ts`
**Scopo**: Logger strutturato per Cloud Functions.

**Classe**: `Logger`

**Funzionalità**:
- Log levels: debug, info, warn, error
- Structured logging JSON
- Child loggers per context

---

#### `functions/src/lib/secrets.ts`
**Scopo**: Gestione secrets Firebase.

**Secrets**:
- `JWT_SECRET`
- `GEMINI_API_KEY`
- `CLAUDE_API_KEY`
- `OPENAI_API_KEY`

---

## WordPress Plugin (Frontend)

### Entry Point

#### `creator-core.php`
**Scopo**: File principale del plugin WordPress.

**Funzionalità**:
- Definizione costanti plugin
- Inizializzazione autoloader
- Hook attivazione/disattivazione
- Avvio plugin

**Interazioni**:
- → `includes/Autoloader.php`
- → `includes/Activator.php`
- → `includes/Deactivator.php`
- → `includes/Loader.php`

---

#### `includes/Loader.php`
**Scopo**: Loader principale che orchestra tutti i componenti.

**Funzionalità**:
- Registrazione hooks WordPress
- Inizializzazione moduli
- Caricamento assets

**Interazioni**:
- → Tutti i moduli in `includes/`

---

#### `includes/Autoloader.php`
**Scopo**: PSR-4 autoloader per classi plugin.

**Funzionalità**:
- Mapping namespace → directory
- Lazy loading classi

---

#### `includes/Activator.php`
**Scopo**: Logica attivazione plugin.

**Funzionalità**:
- Creazione tabelle database
- Setup opzioni default
- Flush rewrite rules

**Interazioni**:
- → `database/migrations.php`

---

#### `includes/Deactivator.php`
**Scopo**: Logica disattivazione plugin.

**Funzionalità**:
- Cleanup scheduled events
- Flush cache

---

### Admin Module

#### `includes/Admin/Dashboard.php`
**Scopo**: Dashboard principale admin.

**Funzionalità**:
- Pagina menu principale
- Widget statistiche
- Overview utilizzo

**Interazioni**:
- → `templates/admin-dashboard.php`

---

#### `includes/Admin/Settings.php`
**Scopo**: Pagina impostazioni plugin.

**Funzionalità**:
- Form settings API key
- Configurazione proxy
- Opzioni avanzate

**Interazioni**:
- → `templates/settings.php`
- → WordPress Settings API

---

#### `includes/Admin/SetupWizard.php`
**Scopo**: Wizard configurazione iniziale.

**Funzionalità**:
- Step-by-step setup
- Validazione licenza
- Test connessione

**Interazioni**:
- → `templates/setup-wizard.php`
- → `Integrations/ProxyClient.php`

---

### API Module

#### `includes/API/REST_API.php`
**Scopo**: Registrazione REST API endpoints WordPress.

**Funzionalità**:
- Registrazione routes
- Inizializzazione controllers
- CORS handling

**Endpoints registrati**:
- `/creator/v1/chat/*` - Chat
- `/creator/v1/actions/*` - Azioni
- `/creator/v1/context/*` - Contesto
- `/creator/v1/files/*` - Files
- `/creator/v1/database/*` - Database
- `/creator/v1/analyze/*` - Analisi
- `/creator/v1/elementor/*` - Elementor
- `/creator/v1/plugins/*` - Plugin
- `/creator/v1/system/*` - Sistema

**Interazioni**:
- → `API/Controllers/*`

---

#### `includes/API/Controllers/BaseController.php`
**Scopo**: Classe base per tutti i controller REST.

**Funzionalità**:
- Validazione permessi
- Response formatting
- Error handling

---

#### `includes/API/Controllers/ChatController.php`
**Scopo**: Controller per endpoint chat.

**Endpoints**:
- `POST /chat/send` - Invia messaggio
- `GET /chat/history` - Storico conversazione
- `DELETE /chat/clear` - Cancella chat

**Interazioni**:
- → `Chat/MessageHandler.php`
- → `Integrations/ProxyClient.php`

---

#### `includes/API/Controllers/ActionController.php`
**Scopo**: Controller per esecuzione azioni.

**Endpoints**:
- `POST /actions/execute` - Esegui azione
- `POST /actions/{id}/rollback` - Rollback
- `GET /actions/{id}` - Dettagli azione

**Interazioni**:
- → `Executor/ActionDispatcher.php`
- → `Backup/Rollback.php`

---

#### `includes/API/Controllers/ContextController.php`
**Scopo**: Controller per gestione contesto.

**Endpoints**:
- `GET /context/current` - Contesto attuale
- `POST /context/refresh` - Aggiorna contesto

**Interazioni**:
- → `Context/CreatorContext.php`

---

#### `includes/API/Controllers/FileController.php`
**Scopo**: Controller per operazioni file.

**Endpoints**:
- `GET /files/list` - Lista file
- `GET /files/read` - Leggi file
- `POST /files/write` - Scrivi file
- `DELETE /files/delete` - Elimina file

**Interazioni**:
- → `Development/FileSystemManager.php`

---

#### `includes/API/Controllers/DatabaseController.php`
**Scopo**: Controller per operazioni database.

**Endpoints**:
- `GET /database/tables` - Lista tabelle
- `POST /database/query` - Esegui query
- `GET /database/structure` - Struttura DB

**Interazioni**:
- → `Development/DatabaseManager.php`

---

#### `includes/API/Controllers/ElementorController.php`
**Scopo**: Controller per integrazione Elementor.

**Endpoints**:
- `GET /elementor/pages` - Lista pagine
- `GET /elementor/page/{id}` - Dettagli pagina
- `POST /elementor/page/{id}` - Modifica pagina
- `GET /elementor/widgets` - Widget disponibili

**Interazioni**:
- → `Integrations/ElementorIntegration.php`

---

#### `includes/API/Controllers/PluginController.php`
**Scopo**: Controller per gestione plugin.

**Endpoints**:
- `GET /plugins/list` - Lista plugin
- `POST /plugins/activate` - Attiva plugin
- `POST /plugins/deactivate` - Disattiva plugin

**Interazioni**:
- → `Integrations/PluginDetector.php`

---

#### `includes/API/Controllers/AnalyzeController.php`
**Scopo**: Controller per analisi codice.

**Endpoints**:
- `POST /analyze/code` - Analizza codice
- `POST /analyze/theme` - Analizza tema

**Interazioni**:
- → `Development/CodeAnalyzer.php`

---

#### `includes/API/Controllers/SystemController.php`
**Scopo**: Controller per operazioni di sistema.

**Endpoints**:
- `GET /system/info` - Info sistema
- `GET /system/health` - Health check

---

#### `includes/API/RateLimiter.php`
**Scopo**: Rate limiting locale per API.

**Funzionalità**:
- Transient-based limiting
- Per-endpoint limits

---

### Chat Module

#### `includes/Chat/ChatInterface.php`
**Scopo**: Interfaccia principale chat admin.

**Funzionalità**:
- Registrazione pagina menu
- Enqueue scripts/styles
- Render interfaccia

**Interazioni**:
- → `templates/chat-interface.php`
- → `assets/js/chat-interface.js`
- → `assets/css/chat-interface.css`

---

#### `includes/Chat/MessageHandler.php`
**Scopo**: Gestione messaggi chat.

**Funzionalità**:
- Formattazione messaggi
- Estrazione azioni da risposta AI
- Validazione JSON azioni
- Gestione storico

**Interazioni**:
- → `Context/CreatorContext.php`
- → `Integrations/ProxyClient.php`
- → `Chat/PhaseDetector.php`

---

#### `includes/Chat/PhaseDetector.php`
**Scopo**: Rilevamento fase conversazione.

**Funzionalità**:
- Identifica fase: exploration, planning, execution
- Adatta comportamento AI

---

#### `includes/Chat/ContextCollector.php`
**Scopo**: Raccolta contesto per prompt AI.

**Funzionalità**:
- Aggregazione dati WordPress
- Formattazione per AI
- Caching

**Interazioni**:
- → `Context/*`

---

### Context Module

#### `includes/Context/CreatorContext.php`
**Scopo**: Classe principale per gestione contesto.

**Funzionalità**:
- Aggregazione tutti i contesti
- Formattazione system prompt
- Gestione stato conversazione

**Interazioni**:
- → `Context/ContextLoader.php`
- → `Context/SystemPrompts.php`

---

#### `includes/Context/ContextLoader.php`
**Scopo**: Caricamento lazy dei contesti.

**Funzionalità**:
- Loading on-demand
- Prioritizzazione contesti

---

#### `includes/Context/ContextCache.php`
**Scopo**: Caching contesti.

**Funzionalità**:
- Transient cache
- Invalidation hooks

---

#### `includes/Context/ContextRefresher.php`
**Scopo**: Aggiornamento contesti.

**Funzionalità**:
- Refresh periodico
- Force refresh

---

#### `includes/Context/SystemPrompts.php`
**Scopo**: Template system prompts per AI.

**Funzionalità**:
- Prompt base
- Prompt per integrazione
- Prompt per task type

---

#### `includes/Context/PluginDocsRepository.php`
**Scopo**: Repository documentazione plugin.

**Funzionalità**:
- Recupero docs da Firebase
- Caching locale

**Interazioni**:
- → Firebase `plugin_docs`

---

#### `includes/Context/ThinkingLogger.php`
**Scopo**: Logging thinking/reasoning AI.

**Funzionalità**:
- Log extended thinking
- Display in UI

---

### Executor Module

#### `includes/Executor/CodeExecutor.php`
**Scopo**: Esecuzione codice PHP generato da AI.

**Funzionalità**:
- Sandboxing esecuzione
- Validazione codice
- Cattura output/errori

**Interazioni**:
- → `Executor/ErrorHandler.php`
- → `Backup/SnapshotManager.php`

---

#### `includes/Executor/ActionDispatcher.php`
**Scopo**: Dispatcher per azioni WordPress.

**Funzionalità**:
- Routing azioni a handler specifici
- Validazione payload azioni
- Orchestrazione esecuzione

**Azioni supportate**:
- `create_post`, `update_post`, `delete_post`
- `create_page`, `update_page`
- `update_option`, `delete_option`
- `execute_php`, `execute_sql`
- `create_file`, `update_file`, `delete_file`
- `install_plugin`, `activate_plugin`
- `elementor_update_page`
- `woocommerce_create_product`

**Interazioni**:
- → `Executor/ActionHandler.php`
- → `Executor/Handlers/*`
- → `Backup/SnapshotManager.php`

---

#### `includes/Executor/ActionHandler.php`
**Scopo**: Handler base per azioni.

**Funzionalità**:
- Template method per esecuzione
- Pre/post hooks
- Logging

---

#### `includes/Executor/Handlers/ExecutePHPHandler.php`
**Scopo**: Handler per esecuzione codice PHP.

**Funzionalità**:
- Esecuzione sandboxed
- Validazione sicurezza
- Output capture

---

#### `includes/Executor/ActionResult.php`
**Scopo**: Value object per risultato azione.

**Proprietà**:
```php
class ActionResult {
  public bool $success;
  public string $message;
  public mixed $data;
  public ?string $error;
  public bool $can_rollback;
}
```

---

#### `includes/Executor/OperationFactory.php`
**Scopo**: Factory per creazione operazioni.

**Funzionalità**:
- Istanziazione handler corretto
- Dependency injection

---

#### `includes/Executor/ExecutionVerifier.php`
**Scopo**: Verifica post-esecuzione.

**Funzionalità**:
- Validazione risultato
- Check integrità

---

#### `includes/Executor/ErrorHandler.php`
**Scopo**: Gestione errori esecuzione.

**Funzionalità**:
- Cattura errori PHP
- Formatting errori
- Recovery

---

#### `includes/Executor/CustomFileManager.php`
**Scopo**: Gestione file custom.

**Funzionalità**:
- CRUD file tema/plugin
- Backup automatico

---

#### `includes/Executor/CustomCodeLoader.php`
**Scopo**: Loader per codice custom.

**Funzionalità**:
- Caricamento snippet
- Esecuzione hooks

---

### Backup Module

#### `includes/Backup/SnapshotManager.php`
**Scopo**: Gestione snapshot per rollback.

**Funzionalità**:
- Creazione snapshot pre-azione
- Salvataggio stato
- Cleanup vecchi snapshot

**Interazioni**:
- → Tabella `{prefix}_creator_snapshots`

---

#### `includes/Backup/Rollback.php`
**Scopo**: Esecuzione rollback azioni.

**Funzionalità**:
- Ripristino da snapshot
- Rollback parziale/totale

**Interazioni**:
- → `Backup/SnapshotManager.php`

---

#### `includes/Backup/DeltaBackup.php`
**Scopo**: Backup incrementale.

**Funzionalità**:
- Delta detection
- Compressione

---

### Integrations Module

#### `includes/Integrations/ProxyClient.php`
**Scopo**: Client HTTP per comunicazione con Firebase Functions.

**Funzionalità**:
- Autenticazione JWT
- Retry con backoff
- Error handling
- Streaming SSE

**Metodi principali**:
```php
sendMessage(string $message, array $context): array
validateLicense(string $licenseKey): array
getAnalytics(): array
```

**Interazioni**:
- → Firebase Functions API
- → `lib/jwt.ts` (token verification)

---

#### `includes/Integrations/PluginDetector.php`
**Scopo**: Rilevamento plugin attivi.

**Funzionalità**:
- Scan plugin installati
- Rilevamento versioni
- Compatibilità check

**Plugin rilevati**:
- Elementor
- WooCommerce
- ACF
- Rank Math
- WPCode
- LiteSpeed

---

#### `includes/Integrations/ElementorIntegration.php`
**Scopo**: Integrazione Elementor.

**Funzionalità**:
- Lettura/scrittura pagine Elementor
- Manipolazione widget
- Template management

**Interazioni**:
- → Elementor API
- → `Integrations/ElementorPageBuilder.php`
- → `Integrations/ElementorActionHandler.php`

---

#### `includes/Integrations/ElementorPageBuilder.php`
**Scopo**: Builder per pagine Elementor.

**Funzionalità**:
- Costruzione struttura pagina
- Widget factory
- Layout management

---

#### `includes/Integrations/ElementorActionHandler.php`
**Scopo**: Handler azioni Elementor.

**Funzionalità**:
- Creazione/modifica sezioni
- Update widget
- Style management

---

#### `includes/Integrations/ElementorSchemaLearner.php`
**Scopo**: Apprendimento schema Elementor.

**Funzionalità**:
- Estrazione schema pagine esistenti
- Learning widget patterns

---

#### `includes/Integrations/WooCommerceIntegration.php`
**Scopo**: Integrazione WooCommerce.

**Funzionalità**:
- CRUD prodotti
- Gestione ordini
- Categorie/attributi

---

#### `includes/Integrations/ACFIntegration.php`
**Scopo**: Integrazione Advanced Custom Fields.

**Funzionalità**:
- Lettura/scrittura campi ACF
- Gruppi di campi
- Repeater fields

---

#### `includes/Integrations/RankMathIntegration.php`
**Scopo**: Integrazione Rank Math SEO.

**Funzionalità**:
- Meta SEO
- Schema markup
- Redirects

---

#### `includes/Integrations/WPCodeIntegration.php`
**Scopo**: Integrazione WPCode.

**Funzionalità**:
- Gestione snippet
- Attivazione/disattivazione

---

#### `includes/Integrations/LiteSpeedIntegration.php`
**Scopo**: Integrazione LiteSpeed Cache.

**Funzionalità**:
- Purge cache
- Ottimizzazione

---

### Audit Module

#### `includes/Audit/AuditLogger.php`
**Scopo**: Logging audit operazioni.

**Funzionalità**:
- Log tutte le azioni
- Timestamps
- User tracking

**Interazioni**:
- → Tabella `{prefix}_creator_audit_log`

---

#### `includes/Audit/OperationTracker.php`
**Scopo**: Tracking operazioni in corso.

**Funzionalità**:
- Stato operazioni
- Progress tracking

---

### Development Module

#### `includes/Development/CodeAnalyzer.php`
**Scopo**: Analisi codice PHP/JS.

**Funzionalità**:
- Static analysis
- Security scan
- Dependency detection

---

#### `includes/Development/DatabaseManager.php`
**Scopo**: Gestione database WordPress.

**Funzionalità**:
- Query builder
- Schema management
- Migration helper

---

#### `includes/Development/FileSystemManager.php`
**Scopo**: Gestione file system.

**Funzionalità**:
- CRUD file
- Permission management
- Path validation

---

#### `includes/Development/PluginGenerator.php`
**Scopo**: Generazione plugin WordPress.

**Funzionalità**:
- Scaffolding plugin
- Template generation

---

### Permission Module

#### `includes/Permission/CapabilityChecker.php`
**Scopo**: Verifica capabilities utente.

**Funzionalità**:
- Check permessi
- Role validation

---

#### `includes/Permission/RoleMapper.php`
**Scopo**: Mapping ruoli/capabilities.

**Funzionalità**:
- Role → capabilities mapping
- Custom capabilities

---

### User Module

#### `includes/User/UserProfile.php`
**Scopo**: Gestione profilo utente Creator.

**Funzionalità**:
- Preferenze utente
- Storico utilizzo

---

### Templates

#### `templates/chat-interface.php`
**Scopo**: Template HTML interfaccia chat.

**Contenuto**:
- Container chat
- Input area
- Message list
- Action cards area

---

#### `templates/admin-dashboard.php`
**Scopo**: Template dashboard admin.

**Contenuto**:
- Widget statistiche
- Quick actions
- Overview

---

#### `templates/settings.php`
**Scopo**: Template pagina settings.

**Contenuto**:
- Form configurazione
- Test connection button

---

#### `templates/setup-wizard.php`
**Scopo**: Template setup wizard.

**Contenuto**:
- Step wizard
- License validation form

---

#### `templates/action-card.php`
**Scopo**: Template card azione.

**Contenuto**:
- Dettagli azione
- Pulsanti execute/cancel/rollback

---

#### `templates/plugin-detector.php`
**Scopo**: Template detector plugin.

**Contenuto**:
- Lista plugin rilevati
- Status compatibilità

---

### Assets JavaScript

#### `assets/js/chat-interface.js`
**Scopo**: JavaScript principale interfaccia chat.

**Oggetti**:
- `CreatorChat` - Gestione chat
- `CreatorThinking` - SSE streaming thinking

**Funzionalità**:
- Invio/ricezione messaggi
- Rendering risposte
- Gestione azioni
- Model toggle (Gemini/Claude)
- File attachments
- SSE streaming

**Interazioni**:
- → REST API `/creator/v1/chat/*`
- → `action-handler.js`

---

#### `assets/js/action-handler.js`
**Scopo**: Gestione esecuzione azioni.

**Oggetto**: `CreatorActionHandler`

**Funzionalità**:
- Execute singola/multiple azioni
- Queue management
- Rollback
- UI state updates

**Stati azione**:
- `pending` - In attesa
- `executing` - In esecuzione
- `completed` - Completata
- `failed` - Fallita
- `rolled_back` - Rollback eseguito

**Interazioni**:
- → REST API `/creator/v1/actions/*`

---

### Database

#### `database/migrations.php`
**Scopo**: Migrazioni database WordPress.

**Tabelle create**:
- `{prefix}_creator_snapshots` - Snapshot per rollback
- `{prefix}_creator_audit_log` - Log audit
- `{prefix}_creator_conversations` - Storico conversazioni

---

### Tests

#### `tests/Unit/*.php`
Test unitari per componenti individuali.

#### `tests/Integration/*.php`
Test di integrazione per flussi completi.

#### `tests/bootstrap.php`
Bootstrap per PHPUnit.

#### `tests/stubs/wordpress-stubs.php`
Stub funzioni WordPress per testing.

---

## Flusso dei Dati e Interazioni

### Flusso Messaggio Chat

```
1. User types message in chat interface
   │
   ▼
2. chat-interface.js: CreatorChat.sendMessage()
   │
   ▼
3. REST API: POST /creator/v1/chat/send
   │
   ▼
4. ChatController.php → MessageHandler.php
   │
   ▼
5. ContextCollector.php collects WordPress context
   │
   ▼
6. ProxyClient.php sends to Firebase
   │
   ▼
7. Firebase: routeRequest → auth middleware → rate limit
   │
   ▼
8. AIRouter.ts routes to optimal provider
   │
   ▼
9. ClaudeProvider/GeminiProvider/OpenAIProvider generates response
   │
   ▼
10. Response returned to WordPress
    │
    ▼
11. MessageHandler.php extracts actions from response
    │
    ▼
12. Response rendered in chat interface
    │
    ▼
13. If actions present: ActionDispatcher executes them
```

### Flusso Esecuzione Azione

```
1. User clicks "Execute" on action card
   │
   ▼
2. action-handler.js: CreatorActionHandler.executeAction()
   │
   ▼
3. REST API: POST /creator/v1/actions/execute
   │
   ▼
4. ActionController.php → ActionDispatcher.php
   │
   ▼
5. SnapshotManager.php creates pre-action snapshot
   │
   ▼
6. ActionHandler executes action (create_post, execute_php, etc.)
   │
   ▼
7. ExecutionVerifier.php validates result
   │
   ▼
8. AuditLogger.php logs action
   │
   ▼
9. ActionResult returned to frontend
   │
   ▼
10. UI updated with success/failure status
```

### Flusso Rollback

```
1. User clicks "Rollback" on completed action
   │
   ▼
2. action-handler.js: CreatorActionHandler.rollbackAction()
   │
   ▼
3. REST API: POST /creator/v1/actions/{id}/rollback
   │
   ▼
4. ActionController.php → Rollback.php
   │
   ▼
5. SnapshotManager.php retrieves snapshot
   │
   ▼
6. Rollback.php restores previous state
   │
   ▼
7. AuditLogger.php logs rollback
   │
   ▼
8. UI updated with rollback status
```

### Flusso Validazione Licenza

```
1. WordPress plugin activation / Setup Wizard
   │
   ▼
2. ProxyClient.php → validateLicense()
   │
   ▼
3. Firebase: validateLicense function
   │
   ▼
4. licensing.ts validates against Firestore
   │
   ▼
5. JWT token generated and returned
   │
   ▼
6. Token stored in WordPress options
   │
   ▼
7. Subsequent requests use JWT for auth
```

---

## Diagrammi di Architettura

### Diagramma Componenti

```
┌─────────────────────────────────────────────────────────────────┐
│                     WORDPRESS ADMIN                              │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                  Chat Interface                          │    │
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐    │    │
│  │  │ Message │  │ Action  │  │ Context │  │ Model   │    │    │
│  │  │ Input   │  │ Cards   │  │ Display │  │ Toggle  │    │    │
│  │  └─────────┘  └─────────┘  └─────────┘  └─────────┘    │    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                    REST API Layer                        │    │
│  │  /chat  /actions  /context  /files  /elementor  /system │    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Business Logic                         │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐   │    │
│  │  │ Message  │ │ Action   │ │ Context  │ │ Snapshot │   │    │
│  │  │ Handler  │ │Dispatcher│ │ Loader   │ │ Manager  │   │    │
│  │  └──────────┘ └──────────┘ └──────────┘ └──────────┘   │    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Integrations                           │    │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐│    │
│  │  │Elementor│ │WooCom │ │  ACF   │ │RankMath│ │LiteSpd ││    │
│  │  └────────┘ └────────┘ └────────┘ └────────┘ └────────┘│    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                    ProxyClient                           │    │
│  │              (HTTP/JWT Communication)                    │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                               │
                               │ HTTPS + JWT
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                    FIREBASE CLOUD FUNCTIONS                      │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                    API Endpoints                         │    │
│  │  validateLicense  routeRequest  submitTask  getAnalytics│    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                    Middleware                            │    │
│  │            Auth (JWT)     Rate Limiting                  │    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                    Services                              │    │
│  │  AIRouter  Licensing  CostCalculator  JobProcessor      │    │
│  └─────────────────────────────────────────────────────────┘    │
│                              │                                   │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                    AI Providers                          │    │
│  │      Claude          Gemini          OpenAI              │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │      FIRESTORE      │
                    │   licenses  jobs    │
                    │   usage    audits   │
                    └─────────────────────┘
```

### Matrice Interazioni File

| Componente | Dipende da | Usato da |
|------------|------------|----------|
| `creator-core.php` | Loader, Activator, Deactivator | WordPress |
| `Loader.php` | Tutti i moduli includes/ | creator-core.php |
| `ProxyClient.php` | - | ChatController, MessageHandler |
| `MessageHandler.php` | ProxyClient, CreatorContext | ChatController |
| `ActionDispatcher.php` | SnapshotManager, ActionHandlers | ActionController |
| `SnapshotManager.php` | - | ActionDispatcher, Rollback |
| `CreatorContext.php` | ContextLoader, SystemPrompts | MessageHandler |
| `chat-interface.js` | action-handler.js | ChatInterface.php |
| `action-handler.js` | - | chat-interface.js |
| `routeRequest.ts` | auth, rateLimit, ModelService | index.ts |
| `AIRouter.ts` | Providers (Claude, Gemini, OpenAI) | ModelService |
| `ClaudeProvider.ts` | @anthropic-ai/sdk | AIRouter |

---

## Conclusioni

Il repository Creator AI è un sistema ben architettato che combina:

1. **Backend Serverless Scalabile**: Firebase Cloud Functions con routing AI intelligente e fallback automatico
2. **Plugin WordPress Modulare**: Architettura PSR-4 con separazione chiara delle responsabilità
3. **Multi-Provider AI**: Supporto Claude, Gemini e OpenAI con switching automatico
4. **Sistema di Sicurezza**: JWT authentication, rate limiting, validazione input
5. **Rollback e Audit**: Sistema completo di backup/restore e logging operazioni
6. **Integrazioni Estese**: Supporto per Elementor, WooCommerce, ACF, Rank Math, WPCode, LiteSpeed

La comunicazione tra WordPress e Firebase avviene tramite REST API sicure con autenticazione JWT, mentre l'interfaccia utente fornisce feedback in tempo reale tramite SSE per il "thinking" dell'AI.
