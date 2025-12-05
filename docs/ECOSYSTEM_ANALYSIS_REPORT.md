# Creator Ecosystem - Report Completo di Analisi

**Data:** 5 Dicembre 2025
**Versione:** 2.0.0
**Autore:** Analisi Tecnica Automatica

---

## Indice

1. [Executive Summary](#executive-summary)
2. [Panoramica dell'Architettura](#panoramica-dellarchitettura)
3. [Modello Logico](#modello-logico)
4. [Componenti del Sistema](#componenti-del-sistema)
   - [Backend Firebase Functions](#1-backend-firebase-functions)
   - [Plugin WordPress Creator Core](#2-plugin-wordpress-creator-core)
5. [Mappa Dettagliata dei File](#mappa-dettagliata-dei-file)
6. [Sistema AI e Providers](#sistema-ai-e-providers)
7. [Sistema di Licensing e Autenticazione](#sistema-di-licensing-e-autenticazione)
8. [Job Queue e Task Asincroni](#job-queue-e-task-asincroni)
9. [Integrazioni Esterne](#integrazioni-esterne)
10. [Flusso dei Dati](#flusso-dei-dati)
11. [Punti Critici Identificati](#punti-critici-identificati)
12. [Codice Obsoleto o Da Eliminare](#codice-obsoleto-o-da-eliminare)
13. [Opportunità di Miglioramento](#opportunità-di-miglioramento)
14. [Raccomandazioni](#raccomandazioni)

---

## Executive Summary

**Creator** è un ecosistema AI-powered per WordPress che permette di automatizzare lo sviluppo di siti web attraverso un'interfaccia chat conversazionale. Il sistema è composto da due componenti principali:

1. **Backend AI Proxy** (Firebase Cloud Functions - TypeScript)
2. **Plugin WordPress** (PHP - Creator Core)

### Metriche Chiave

| Metrica | Valore |
|---------|--------|
| Linguaggi Principali | TypeScript, PHP |
| Provider AI Attivi | Google Gemini, Anthropic Claude |
| Provider AI Backup | OpenAI (non attivo nel flusso principale) |
| Integrazioni WordPress | Elementor, ACF, RankMath, WooCommerce, LiteSpeed |
| API Endpoints | 6 (auth, ai, tasks, analytics, plugin-docs) |
| Performance Tiers | 2 (Flow, Craft) |
| Linee di Codice Stimate | ~20,000+ |
| Complessità Architetturale | Alta |

### Cambiamenti dalla Versione 1.0

- **Nuovo sistema di licensing** con JWT authentication
- **Performance Tiers** (Flow/Craft) per ottimizzazione costi/qualità
- **Job Queue** per task asincroni (bulk articles, bulk products, design batch)
- **Analytics endpoint** per monitoraggio costi e utilizzo
- **Plugin Docs** sistema per ricerca documentazione plugin
- **Rimozione routing matrix** - ora usa fallback semplice Gemini ↔ Claude
- **Context Caching** (pianificato ma non ancora implementato)

---

## Panoramica dell'Architettura

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            WordPress Site                                    │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                      Creator Core Plugin                               │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐   │  │
│  │  │    Chat     │  │   REST      │  │  Context    │  │  Elementor  │   │  │
│  │  │  Interface  │→ │    API      │→ │   Loader    │→ │ PageBuilder │   │  │
│  │  └─────────────┘  └──────┬──────┘  └─────────────┘  └─────────────┘   │  │
│  │                          │                                             │  │
│  │  ┌─────────────┐  ┌──────┴──────┐  ┌─────────────┐  ┌─────────────┐   │  │
│  │  │  Snapshot   │  │   Proxy     │  │   Audit     │  │ Permission  │   │  │
│  │  │  Manager    │  │   Client    │  │   Logger    │  │   Checker   │   │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘   │  │
│  │                                                                        │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐   │  │
│  │  │ WooCommerce │  │    ACF      │  │  RankMath   │  │  LiteSpeed  │   │  │
│  │  │ Integration │  │ Integration │  │ Integration │  │ Integration │   │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘   │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                    ↕ HTTPS                                   │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                       Firebase Cloud Functions                               │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          API Endpoints                                 │  │
│  │  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐           │  │
│  │  │ validate-      │  │ route-         │  │ submit-        │           │  │
│  │  │ license        │  │ request        │  │ task           │           │  │
│  │  └────────────────┘  └────────────────┘  └────────────────┘           │  │
│  │  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐           │  │
│  │  │ get-           │  │ analytics      │  │ plugin-        │           │  │
│  │  │ status         │  │                │  │ docs           │           │  │
│  │  └────────────────┘  └────────────────┘  └────────────────┘           │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                        Model Service                                   │  │
│  │  ┌──────────────────┐         ┌──────────────────┐                    │  │
│  │  │  Gemini Provider │ ←────→  │  Claude Provider │                    │  │
│  │  │  (Primary/Fallback)        │  (Primary/Fallback)                   │  │
│  │  └──────────────────┘         └──────────────────┘                    │  │
│  │                    ↓                     ↓                             │  │
│  │  ┌─────────────────────────────────────────────────────────────────┐  │  │
│  │  │              Automatic Fallback Routing                          │  │  │
│  │  │         Gemini fails → try Claude │ Claude fails → try Gemini    │  │  │
│  │  └─────────────────────────────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                        Tier Chain Service                              │  │
│  │  ┌─────────────────────────────────────────────────────────────────┐  │  │
│  │  │  FLOW Mode (0.5 credits)                                        │  │  │
│  │  │  Gemini 2.5 Flash → Claude 4 Sonnet → Validation                │  │  │
│  │  └─────────────────────────────────────────────────────────────────┘  │  │
│  │  ┌─────────────────────────────────────────────────────────────────┐  │  │
│  │  │  CRAFT Mode (2.0 credits)                                       │  │  │
│  │  │  Gemini Flash → Gemini Pro → Claude Opus → Validation           │  │  │
│  │  └─────────────────────────────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│  ┌───────────────────────────────────────────────────────────────────────┐  │
│  │                          Firestore                                     │  │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐       │  │
│  │  │  licenses  │  │  job_queue │  │ audit_logs │  │   cost_    │       │  │
│  │  │            │  │            │  │            │  │  tracking  │       │  │
│  │  └────────────┘  └────────────┘  └────────────┘  └────────────┘       │  │
│  └───────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Modello Logico

### Pattern Architetturale

Il sistema segue un'architettura **Microservices + Plugin Modulare**:

1. **Separation of Concerns**: Backend AI separato dal frontend WordPress
2. **Provider Abstraction**: Interfaccia comune `IAIProvider` per tutti i provider AI
3. **Simple Fallback**: Routing semplificato con fallback automatico Gemini ↔ Claude
4. **Performance Tiers**: Due modalità (Flow/Craft) per bilanciare costi e qualità
5. **JWT Authentication**: Token-based auth per sicurezza API
6. **Event-Driven Jobs**: Firestore triggers per elaborazione asincrona
7. **Audit Trail**: Sistema completo di logging per tracciabilità

### Flusso di Esecuzione Principale

```
User Request → Chat Interface → REST API → License Validation
                                                ↓
                                    AI Proxy (Firebase Functions)
                                                ↓
                                    Rate Limit Check
                                                ↓
                                    Model Service / Tier Chain
                                                ↓
                                    Provider Selection (Gemini/Claude)
                                                ↓
                                    AI Response Generation
                                                ↓
                                    Token & Cost Tracking
                                                ↓
Action Execution ← Action Parser ← Response with Actions
       ↓
Snapshot Creation → Database + File System
       ↓
Response to User ← Result Processing
```

---

## Componenti del Sistema

### 1. Backend Firebase Functions

**Percorso:** `/functions/`

#### 1.1 Entry Point - `src/index.ts`

**Funzione:** Punto di ingresso dell'applicazione Firebase Functions
**Produce:** Export di tutti gli endpoint HTTP e trigger
**Interazione:** Espone le API REST e i trigger Firestore

```typescript
// Endpoint esportati
export { validateLicense } from "./api/auth/validateLicense";
export { routeRequest } from "./api/ai/routeRequest";
export { submitTask } from "./api/tasks/submitTask";
export { getTaskStatus } from "./api/tasks/getStatus";
export { getAnalytics } from "./api/analytics/getAnalytics";
export { getPluginDocs, savePluginDocs, researchPluginDocs } from "./api/pluginDocs/*";
export { processJobQueue } from "./triggers/jobQueueTrigger";
```

---

#### 1.2 API Auth - `src/api/auth/validateLicense.ts`

**Funzione:** Validazione licenze e generazione JWT
**Produce:** Token JWT per autenticazione successive richieste
**Endpoint:** `POST /api/auth/validate-license`

**Request:**
```json
{
  "license_key": "CREATOR-2024-XXXXX-XXXXX",
  "site_url": "https://example.com"
}
```

**Response:**
```json
{
  "success": true,
  "user_id": "user_123",
  "site_token": "eyJhbGciOiJIUzI1NiIs...",
  "plan": "pro",
  "tokens_limit": 50000000,
  "tokens_remaining": 47654322,
  "reset_date": "2025-12-01"
}
```

**Caratteristiche:**
- Rate limiting: 10 req/min per IP
- CORS headers per WordPress
- Validazione site_url
- JWT con scadenza configurabile

---

#### 1.3 API AI Route - `src/api/ai/routeRequest.ts`

**Funzione:** Routing richieste AI con fallback automatico
**Produce:** Risposta AI con contenuto generato e metadati
**Endpoint:** `POST /api/ai/route-request`

**Request:**
```json
{
  "task_type": "TEXT_GEN | CODE_GEN | DESIGN_GEN | ECOMMERCE_GEN",
  "prompt": "string (max 10000 chars)",
  "model": "gemini | claude",
  "context": { /* site context */ },
  "system_prompt": "optional custom system prompt",
  "temperature": 0.7,
  "max_tokens": 8000,
  "files": [{ "name": "img.png", "type": "image/png", "base64": "..." }]
}
```

**Response:**
```json
{
  "success": true,
  "content": "generated content",
  "model": "gemini",
  "model_id": "gemini-3-pro-preview",
  "used_fallback": false,
  "tokens_used": 1250,
  "cost_usd": 0.0942,
  "latency_ms": 2341
}
```

**Caratteristiche:**
- JWT authentication required
- Rate limiting: 100 req/min per license
- Quota checking
- Automatic fallback on provider failure
- Cost tracking in Firestore
- Audit logging

---

#### 1.4 API Tasks - `src/api/tasks/submitTask.ts` & `getStatus.ts`

**Funzione:** Gestione task asincroni per operazioni bulk
**Produce:** Job ID per polling stato

**Submit Task - `POST /api/tasks/submit`:**
```json
{
  "task_type": "bulk_articles | bulk_products | design_batch",
  "task_data": {
    // Per bulk_articles:
    "topics": ["topic1", "topic2"],
    "tone": "professional",
    "language": "en",
    "word_count": 800

    // Per bulk_products:
    "products": [{ "name": "Product", "category": "Category" }]

    // Per design_batch:
    "sections": [{ "name": "Hero", "style": "modern" }]
  }
}
```

**Response:**
```json
{
  "success": true,
  "job_id": "job_f47ac10b-58cc-4372-...",
  "status": "pending",
  "estimated_wait_seconds": 45
}
```

**Get Status - `GET /api/tasks/status/:job_id`:**
```json
{
  "success": true,
  "job_id": "job_...",
  "status": "processing | completed | failed",
  "progress": {
    "progress_percent": 30,
    "items_completed": 3,
    "items_total": 10,
    "current_item_title": "Generating article: WordPress Security"
  },
  "result": { /* when completed */ }
}
```

---

#### 1.5 API Analytics - `src/api/analytics/getAnalytics.ts`

**Funzione:** Dashboard analytics per costi e utilizzo
**Produce:** Metriche aggregate per periodo
**Endpoint:** `GET /api/analytics?period=2025-11&include_trend=true`

**Response:**
```json
{
  "success": true,
  "data": {
    "period": "2025-11",
    "license_id": "CREATOR-2024-XXXXX",
    "total_requests": 342,
    "total_tokens": 635000,
    "total_cost": 2.345,
    "breakdown_by_provider": {
      "gemini": { "tokens": 390000, "cost": 0.758, "requests": 180 },
      "claude": { "tokens": 245000, "cost": 1.587, "requests": 162 }
    },
    "breakdown_by_task": {
      "TEXT_GEN": { "requests": 180, "tokens": 245000, "cost": 0.934 },
      "CODE_GEN": { "requests": 98, "tokens": 234000, "cost": 0.856 }
    },
    "monthly_trend": [ /* 6 months history */ ],
    "provider_stats": { /* efficiency metrics */ }
  }
}
```

---

#### 1.6 Services

##### `src/services/modelService.ts`

**Funzione:** Orchestrazione chiamate AI con fallback
**Produce:** `ModelResponse` con contenuto e metadati

**Caratteristiche:**
- Selezione primario: User choice (Gemini o Claude)
- Fallback automatico al provider alternativo
- System prompt di default per WordPress assistant
- Supporto multimodale (immagini)

```typescript
// Flusso
const result = await modelService.generate({
  model: "gemini",  // o "claude"
  prompt: "...",
  context: {...}
});

// Se Gemini fallisce → prova Claude automaticamente
// Se Claude fallisce → prova Gemini automaticamente
```

##### `src/services/tierChain.ts`

**Funzione:** Chain multi-step per task complessi
**Produce:** `TierChainResponse` con output e step details

**FLOW Mode (0.5 credits):**
```
Step 1: Gemini 2.5 Flash → Context Analysis
Step 2: Claude 4 Sonnet → Implementation
Step 3: Syntactic Validation (no AI cost)
```

**CRAFT Mode (2.0 credits):**
```
Step 1: Gemini 2.5 Flash → Deep Context Analysis
Step 2: Gemini 2.5 Pro → Strategy Generation
Step 3: Claude 4.5 Opus → Implementation
Step 4: Syntactic Validation + Optional AI Validation
```

##### `src/services/licensing.ts`

**Funzione:** Validazione e gestione licenze
**Produce:** `LicenseValidationResult` con token JWT

**Flusso:**
1. Lookup licenza in Firestore
2. Verifica status (active/suspended/expired)
3. Verifica site_url match
4. Check quota disponibile
5. Genera JWT token

##### `src/services/costCalculator.ts`

**Funzione:** Calcolo costi e analytics
**Produce:** Metriche aggregate, comparazioni periodo

##### `src/services/jobProcessor.ts`

**Funzione:** Elaborazione job dalla queue
**Produce:** Risultati task con progress tracking

##### `src/services/aiRouter.ts`

**Funzione:** Utilities per routing (sanitization, validation)
**Produce:** Prompt sanitizzati, validazione

---

#### 1.7 Providers

##### `src/providers/gemini.ts`

**Funzione:** Client Google Gemini
**Modello Default:** `gemini-2.5-pro-preview-05-06`

**Caratteristiche:**
- Retry con exponential backoff
- Native token counting via `countTokens` API
- Safety settings configurabili
- Supporto multimodale (images, PDF)
- Cost calculation automatico

##### `src/providers/claude.ts`

**Funzione:** Client Anthropic Claude
**Modello Default:** `claude-opus-4-5-20251101`

**Caratteristiche:**
- Retry con exponential backoff
- Token counting via tiktoken (cl100k_base)
- Supporto multimodale (images)
- Cost calculation automatico

##### `src/providers/openai.ts`

**Funzione:** Client OpenAI (backup/legacy)
**Modello Default:** `gpt-4o`

**Stato:** Definito ma non utilizzato nel flusso principale (solo per job processor come fallback)

---

#### 1.8 Middleware

##### `src/middleware/auth.ts`

**Funzione:** Autenticazione JWT
**Produce:** `AuthResult` con claims validati

```typescript
interface AuthResult {
  authenticated: boolean;
  claims?: JWTClaims;
  error?: string;
  code?: string;
}
```

**Validazioni:**
- Estrazione Bearer token
- Verifica firma JWT
- Check licenza attiva in Firestore
- Verifica site_url match

##### `src/middleware/rateLimit.ts`

**Funzione:** Rate limiting per IP/license
**Produce:** Headers `X-RateLimit-*`

```typescript
interface RateLimitConfig {
  maxRequests: number;     // Default: 10
  windowSeconds?: number;  // Default: 60
  endpoint: string;
}
```

---

#### 1.9 Triggers

##### `src/triggers/jobQueueTrigger.ts`

**Funzione:** Elaborazione automatica job dalla queue
**Trigger:** `onDocumentCreated("job_queue/{jobId}")`

**Configurazione:**
- Timeout: 540 seconds (9 minutes)
- Memory: 2GiB
- Max instances: 50
- Region: europe-west1

---

#### 1.10 Types

| File | Contenuto |
|------|-----------|
| `AIProvider.ts` | Interface `IAIProvider`, `AIResponse`, pricing tables |
| `Auth.ts` | `ValidateLicenseRequest`, error codes/status maps |
| `JWTClaims.ts` | JWT payload structure |
| `License.ts` | `License`, `LicensePlan` enums |
| `Route.ts` | `RouteRequest`, task types, thresholds |
| `Job.ts` | `Job`, task types, validation functions |
| `Analytics.ts` | `AnalyticsResponse`, `ExtendedAnalytics` |
| `ModelConfig.ts` | `AIModel`, `MODEL_IDS`, `ModelRequest` |
| `PerformanceTier.ts` | `PerformanceTier`, `TIER_CONFIGS`, `TIER_MODELS` |
| `PluginDocs.ts` | Plugin documentation cache types |
| `APIResponse.ts` | Standard API response wrapper |

---

#### 1.11 Library

| File | Funzione |
|------|----------|
| `firestore.ts` | Wrapper Firestore con funzioni CRUD per licenses, jobs, audit, cost tracking |
| `jwt.ts` | Sign/verify JWT, extract Bearer token |
| `logger.ts` | Structured logging con child loggers |
| `secrets.ts` | Firebase Secrets Manager (API keys) |

---

### 2. Plugin WordPress Creator Core

**Percorso:** `/packages/creator-core-plugin/creator-core/`

#### 2.1 Struttura Directory

```
creator-core/
├── creator-core.php              # Main plugin file
├── composer.json                 # PHP dependencies
├── includes/
│   ├── Loader.php               # Component orchestrator
│   ├── Activator.php            # Activation hooks
│   ├── Deactivator.php          # Deactivation hooks
│   ├── API/
│   │   └── REST_API.php         # REST endpoints
│   ├── Admin/
│   │   ├── Dashboard.php        # Admin dashboard
│   │   ├── Settings.php         # Plugin settings
│   │   └── SetupWizard.php      # Setup wizard
│   ├── Chat/
│   │   └── ChatInterface.php    # Chat management
│   ├── Context/
│   │   ├── ContextLoader.php    # WP context collection
│   │   └── ThinkingLogger.php   # AI reasoning log
│   ├── Backup/
│   │   ├── SnapshotManager.php  # Snapshot management
│   │   ├── DeltaBackup.php      # Delta backups
│   │   └── Rollback.php         # Rollback execution
│   ├── Permission/
│   │   ├── CapabilityChecker.php # Permission control
│   │   └── RoleMapper.php       # Role mapping
│   ├── Audit/
│   │   ├── AuditLogger.php      # Audit logging
│   │   └── OperationTracker.php # Operation tracking
│   ├── Executor/
│   │   ├── CodeExecutor.php     # Code execution
│   │   ├── ExecutionVerifier.php # Execution verification
│   │   ├── OperationFactory.php # Operation factory
│   │   ├── CustomFileManager.php # Custom file management
│   │   ├── CustomCodeLoader.php # Custom code loading
│   │   └── ErrorHandler.php     # Error handling
│   ├── Integrations/
│   │   ├── ProxyClient.php      # AI Proxy client
│   │   ├── ElementorPageBuilder.php    # Elementor builder
│   │   ├── ElementorSchemaLearner.php  # Elementor templates
│   │   ├── ElementorIntegration.php    # Elementor base
│   │   ├── ElementorActionHandler.php  # Elementor actions
│   │   ├── ACFIntegration.php   # ACF integration
│   │   ├── RankMathIntegration.php # RankMath SEO
│   │   ├── WooCommerceIntegration.php # WooCommerce
│   │   ├── WPCodeIntegration.php # WPCode snippets
│   │   ├── LiteSpeedIntegration.php # LiteSpeed cache
│   │   └── PluginDetector.php   # Plugin detection
│   └── Development/
│       ├── FileSystemManager.php # File operations
│       ├── PluginGenerator.php  # Plugin generator
│       ├── CodeAnalyzer.php     # Code analysis
│       └── DatabaseManager.php  # Database operations
├── assets/
│   ├── js/
│   └── css/
└── views/
```

---

## Sistema AI e Providers

### Provider Matrix

| Provider | SDK | Modelli Supportati | Stato |
|----------|-----|-------------------|-------|
| **Anthropic Claude** | `@anthropic-ai/sdk` | claude-opus-4-5-20251101, claude-sonnet-4-20250514 | ✅ Attivo |
| **Google Gemini** | `@google/generative-ai` | gemini-2.5-pro-preview-05-06, gemini-2.5-flash, gemini-3-pro-preview | ✅ Attivo |
| **OpenAI** | `openai` | gpt-4o, gpt-4o-mini | ⚠️ Solo backup |

### Pricing Configuration

```typescript
// Gemini
"gemini-2.5-flash-preview-05-20": { input: 0.00015, output: 0.0006 }
"gemini-2.5-pro-preview-05-06": { input: 0.00125, output: 0.005 }

// Claude
"claude-sonnet-4-20250514": { input: 0.003, output: 0.015 }
"claude-opus-4-5-20251101": { input: 0.015, output: 0.075 }

// OpenAI (backup)
"gpt-4o": { input: 0.005, output: 0.015 }
"gpt-4o-mini": { input: 0.00015, output: 0.0006 }
```

### Performance Tiers

| Tier | Credits | Chain | Use Case |
|------|---------|-------|----------|
| **Flow** | 0.5 | Flash → Sonnet → Validation | Iterative work, CSS, snippets |
| **Craft** | 2.0 | Flash → Pro → Opus → Validation | Complex tasks, templates |

---

## Sistema di Licensing e Autenticazione

### License Plans

| Piano | Tokens/Mese | Features |
|-------|-------------|----------|
| `free` | 1,000,000 | Basic AI features |
| `starter` | 5,000,000 | + Priority support |
| `pro` | 50,000,000 | + Bulk operations |
| `enterprise` | 500,000,000 | + Custom models |

### JWT Claims Structure

```typescript
interface JWTClaims {
  license_id: string;    // CREATOR-2024-XXXXX
  user_id: string;
  site_url: string;
  plan: LicensePlan;
  iat: number;           // Issued at
  exp: number;           // Expiration
}
```

### Firestore Collections

| Collection | Descrizione |
|------------|-------------|
| `licenses` | Licenze con quota, status, site_url |
| `job_queue` | Job asincroni pendenti/completati |
| `audit_logs` | Log di tutte le operazioni |
| `cost_tracking` | Tracking costi mensili per provider |
| `rate_limits` | Contatori rate limiting |
| `plugin_docs_cache` | Cache documentazione plugin |

---

## Job Queue e Task Asincroni

### Task Types

| Task | Items | Estimated Time |
|------|-------|----------------|
| `bulk_articles` | Topics array | 30s × item |
| `bulk_products` | Products array | 20s × item |
| `design_batch` | Sections array | 45s × item |

### Job States

```
pending → processing → completed
                    ↘ failed
```

### Progress Tracking

```typescript
interface JobProgress {
  progress_percent: number;
  items_completed: number;
  items_total: number;
  current_item_index: number;
  current_item_title: string;
  eta_seconds: number;
}
```

---

## Integrazioni Esterne

### WordPress Plugins

| Plugin | Integrazione | File |
|--------|--------------|------|
| **Elementor** | Page builder, widgets, sections | `ElementorPageBuilder.php` |
| **ACF** | Custom fields, field groups | `ACFIntegration.php` |
| **RankMath** | SEO metadata | `RankMathIntegration.php` |
| **WooCommerce** | Products, orders | `WooCommerceIntegration.php` |
| **LiteSpeed** | Cache purging | `LiteSpeedIntegration.php` |
| **WPCode** | Snippets management | `WPCodeIntegration.php` |

### Firebase Services

| Servizio | Utilizzo |
|----------|----------|
| **Cloud Functions** | API endpoints |
| **Firestore** | Data persistence |
| **Secrets Manager** | API keys storage |

---

## Flusso dei Dati

### 1. Flusso License Validation

```
WordPress Plugin          Firebase Functions
      │                         │
      │  POST /validate-license │
      │ ─────────────────────── │
      │  license_key, site_url  │
      │                         ├─→ Check Firestore licenses
      │                         ├─→ Verify status = active
      │                         ├─→ Verify site_url match
      │                         ├─→ Generate JWT
      │  ← ─────────────────────│
      │  site_token, plan, quota│
      │                         │
```

### 2. Flusso AI Request

```
WordPress Plugin          Firebase Functions          AI Provider
      │                         │                         │
      │  POST /route-request    │                         │
      │  Authorization: Bearer  │                         │
      │ ─────────────────────── │                         │
      │                         ├─→ Verify JWT            │
      │                         ├─→ Check rate limit      │
      │                         ├─→ Check quota           │
      │                         │                         │
      │                         │  Generate request       │
      │                         │ ─────────────────────── │
      │                         │                         ├─→ Process
      │                         │  ← ───────────────────  │
      │                         │  AI Response            │
      │                         │                         │
      │                         ├─→ Update tokens_used    │
      │                         ├─→ Update cost_tracking  │
      │                         ├─→ Create audit_log      │
      │  ← ─────────────────────│                         │
      │  content, tokens, cost  │                         │
```

### 3. Flusso Async Task

```
WordPress               Firebase API            Firestore Trigger
    │                        │                        │
    │ POST /tasks/submit     │                        │
    │ ────────────────────── │                        │
    │                        ├─→ Create job_queue doc │
    │ ← ──────────────────── │                        │
    │ job_id                 │                        │
    │                        │                        │
    │                        │        onDocumentCreated
    │                        │ ←─────────────────────│
    │                        │                        │
    │                        │        processJob()    │
    │                        │        update progress │
    │                        │        store result    │
    │                        │                        │
    │ GET /tasks/status      │                        │
    │ ────────────────────── │                        │
    │                        ├─→ Read job_queue doc   │
    │ ← ──────────────────── │                        │
    │ status, progress       │                        │
```

---

## Punti Critici Identificati

### 1. **Inconsistenza Modelli AI** ⚠️ ALTO

**File multipli con configurazioni diverse:**

| File | Modello Gemini | Modello Claude |
|------|----------------|----------------|
| `ModelConfig.ts` | `gemini-3-pro-preview` | `claude-sonnet-4-20250514` |
| `PerformanceTier.ts` | `gemini-2.5-flash`, `gemini-2.5-pro` | `claude-sonnet-4-20250514`, `claude-opus-4-5-20251101` |
| `providers/gemini.ts` | `gemini-2.5-pro-preview-05-06` | - |
| `providers/claude.ts` | - | `claude-opus-4-5-20251101` |

**Rischio:** Errori runtime per modelli non esistenti
**Azione:** Unificare configurazione modelli in un unico file source-of-truth

---

### 2. **OpenAI Provider Non Utilizzato** ⚠️ MEDIO

**File:** `providers/openai.ts`, `types/AIProvider.ts`
**Problema:** Provider definito ma non attivamente utilizzato nel flusso principale
**Impatto:** Codice morto, confusione, manutenzione inutile
**Azione:** Decidere se rimuovere o reintegrare

---

### 3. **Context Caching Non Implementato** ⚠️ MEDIO

**File:** `src/services/contextCache.ts` (non esiste)
**Problema:** Riferimenti a context caching nei commenti ma file non presente
**Impatto:** Performance non ottimizzata su richieste ripetute
**Azione:** Implementare o rimuovere riferimenti

---

### 4. **REST Controller Mancante** ⚠️ BASSO

**File:** `src/api/rest/restController.ts` (non esiste)
**Problema:** Riferimento in index.ts ma file non presente
**Azione:** Verificare se necessario o rimuovere riferimento

---

### 5. **Rate Limiting su Firestore** ⚠️ MEDIO

**File:** `lib/firestore.ts`
**Problema:** Rate limiting basato su documenti Firestore (costo, latenza)
**Impatto:** Costi Firestore elevati con alto traffico
**Soluzione:** Considerare Redis/Memcached per rate limiting

---

### 6. **Mancanza Test Automatizzati** ⚠️ ALTO

**Problema:** Nessun file di test identificato nel progetto
**Impatto:** Rischio regressioni, difficoltà refactoring
**Azione:** Implementare test suite (Jest per TypeScript, PHPUnit per PHP)

---

## Codice Obsoleto o Da Eliminare

### 1. **Modelli AI Legacy** 🗑️

**File:** `functions/src/types/AIProvider.ts`

```typescript
// DA RIMUOVERE - Modelli obsoleti
"claude-3-5-sonnet-20241022"
"gemini-1.5-flash"
"gemini-1.5-pro"
"gemini-2.0-flash-exp"
```

**Azione:** Rimuovere dopo verifica utilizzo zero in production

---

### 2. **ModelConfig vs PerformanceTier Duplicazione** 🗑️

**File:** `types/ModelConfig.ts` e `types/PerformanceTier.ts`

Entrambi definiscono modelli ma con valori diversi. Consolidare in un unico file.

---

### 3. **OpenAI Integration (se non utilizzata)** 🗑️

Se OpenAI non è più nel flusso principale:
- `providers/openai.ts` → Rimuovere o marcare come deprecated
- Pricing OpenAI in `AIProvider.ts` → Rimuovere

---

### 4. **Gemini 3 Pro Preview** 🗑️

**File:** `types/ModelConfig.ts`

```typescript
MODEL_IDS = {
  gemini: "gemini-3-pro-preview",  // Non esiste questo modello
```

**Azione:** Verificare e correggere con modello valido

---

## Opportunità di Miglioramento

### 1. **Unificazione Configurazione Modelli** 📈

Creare un singolo file `config/models.ts`:

```typescript
export const AI_MODELS = {
  gemini: {
    default: "gemini-2.5-pro-preview-05-06",
    flash: "gemini-2.5-flash-preview-05-20",
    pro: "gemini-2.5-pro-preview-05-06",
  },
  claude: {
    default: "claude-sonnet-4-20250514",
    sonnet: "claude-sonnet-4-20250514",
    opus: "claude-opus-4-5-20251101",
  },
};
```

---

### 2. **Implementare Context Caching** 🚀

```typescript
// services/contextCache.ts
import { createHash } from "crypto";

interface CachedContext {
  hash: string;
  context: Record<string, unknown>;
  cachedAt: number;
  ttl: number;
}

class ContextCache {
  private cache: Map<string, CachedContext> = new Map();

  async get(licenseId: string, contextKeys: string[]): Promise<CachedContext | null> {
    const hash = this.hashContext(contextKeys);
    const cached = this.cache.get(`${licenseId}:${hash}`);

    if (cached && Date.now() < cached.cachedAt + cached.ttl) {
      return cached;
    }
    return null;
  }

  set(licenseId: string, context: Record<string, unknown>, ttl = 300000): void {
    const hash = this.hashContext(Object.keys(context));
    this.cache.set(`${licenseId}:${hash}`, {
      hash,
      context,
      cachedAt: Date.now(),
      ttl,
    });
  }
}
```

---

### 3. **Test Suite** 🧪

```
functions/
├── src/
└── tests/
    ├── unit/
    │   ├── providers/
    │   │   ├── gemini.test.ts
    │   │   └── claude.test.ts
    │   ├── services/
    │   │   ├── modelService.test.ts
    │   │   └── licensing.test.ts
    │   └── middleware/
    │       ├── auth.test.ts
    │       └── rateLimit.test.ts
    └── integration/
        ├── api.test.ts
        └── jobs.test.ts
```

---

### 4. **Monitoring & Alerting** 📊

Implementare metriche per:
- Error rate per provider
- Latenza media per endpoint
- Costi giornalieri/settimanali
- Rate limit hits
- Job queue depth

---

### 5. **Circuit Breaker Pattern** 🔌

```typescript
class ProviderCircuitBreaker {
  private failures: Map<string, number> = new Map();
  private lastFailure: Map<string, number> = new Map();
  private readonly threshold = 5;
  private readonly resetTime = 60000; // 1 min

  isOpen(provider: string): boolean {
    const failures = this.failures.get(provider) || 0;
    const lastFail = this.lastFailure.get(provider) || 0;

    if (failures >= this.threshold) {
      if (Date.now() - lastFail > this.resetTime) {
        this.failures.set(provider, 0);
        return false;
      }
      return true;
    }
    return false;
  }

  recordFailure(provider: string): void {
    const failures = (this.failures.get(provider) || 0) + 1;
    this.failures.set(provider, failures);
    this.lastFailure.set(provider, Date.now());
  }
}
```

---

## Raccomandazioni

### Priorità Alta (Immediato)

| # | Azione | File/Area | Impatto |
|---|--------|-----------|---------|
| 1 | Unificare configurazione modelli AI | `types/ModelConfig.ts`, `types/PerformanceTier.ts` | Elimina errori runtime |
| 2 | Correggere `gemini-3-pro-preview` | `types/ModelConfig.ts` | Fix critical bug |
| 3 | Decidere su OpenAI provider | `providers/openai.ts` | Riduce codice morto |
| 4 | Aggiungere test unitari base | Nuovo `tests/` | Previene regressioni |

### Priorità Media (2-4 settimane)

| # | Azione | File/Area | Impatto |
|---|--------|-----------|---------|
| 5 | Implementare context caching | Nuovo `services/contextCache.ts` | Performance +30% |
| 6 | Migrare rate limiting a Redis | `middleware/rateLimit.ts` | Costi Firestore -50% |
| 7 | Rimuovere modelli AI legacy | `types/AIProvider.ts` | Pulizia codebase |
| 8 | Aggiungere monitoring/alerting | Nuovo infra | Proactive issue detection |

### Priorità Bassa (Backlog)

| # | Azione | File/Area | Impatto |
|---|--------|-----------|---------|
| 9 | Implementare circuit breaker | `services/modelService.ts` | Resilienza |
| 10 | Generare OpenAPI spec | Nuovo `docs/api/` | Developer experience |
| 11 | Documentazione architettura | `docs/ARCHITECTURE.md` | Onboarding |

---

## Conclusioni

L'ecosistema Creator v2.0 rappresenta un'evoluzione significativa con:

### Punti di Forza ✅

- **Architettura pulita** con separazione backend/frontend
- **Sistema di licensing robusto** con JWT authentication
- **Fallback automatico** tra provider AI
- **Performance Tiers** per ottimizzazione costi/qualità
- **Job Queue** per operazioni asincrone
- **Analytics completo** per monitoraggio costi
- **Audit trail** dettagliato

### Aree di Miglioramento ⚠️

- **Inconsistenza configurazione modelli** tra file diversi
- **OpenAI provider** non utilizzato ma presente
- **Context caching** promesso ma non implementato
- **Mancanza test automatizzati**
- **Rate limiting costoso** su Firestore

### Raccomandazione Strategica

Concentrarsi sulla **stabilizzazione** prima di nuove feature:
1. Unificare configurazioni modelli
2. Implementare test suite base
3. Aggiungere monitoring
4. Poi procedere con ottimizzazioni (caching, circuit breaker)

---

*Report generato automaticamente - Versione 2.0.0*
*Data: 5 Dicembre 2025*
