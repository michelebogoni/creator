# Creator AI Proxy - Roadmap Deliverability-Focused

## Guida Strutturale per Claude Code e Gemini

**Versione:** 3.0 (Deliverability-Focused)\
**Stack:** Firebase + Node.js 18+\
**Audience:** Claude Code, Gemini, Human Developers

## 🎯 Visione Complessiva

Creator è un **sistema di proxy AI centralizzato** che:

-   Gestisce **licenze e autenticazione** per siti WordPress

-   **Instrrada richieste** al provider AI ottimale (OpenAI, Gemini,
    > Claude)

-   Traccia **costi e quota** per user

-   Elabora **task asincroni** (bulk operations)

-   Fornisce **logging completo** per audit

**Architettura Minima:**

WordPress Site

↓ (HTTPS)

Firebase Cloud Functions (Proxy)

├── POST /api/auth/validate-license

├── POST /api/ai/route-request

├── POST /api/tasks/submit

└── GET /api/tasks/status/:id

↓

\[Firestore DB\] \[OpenAI\] \[Gemini\] \[Claude\]

## 🏗️ MILESTONE 1: Firebase Project Setup & Structure

### Obiettivo

Creare una base Firebase completamente configurata con:

-   Firestore database (collections e indexes)

-   Firebase Functions runtime

-   Secrets management

-   Repository GitHub con CI/CD

### How-To per Cloud Developer

**Step 1: Creare Firebase Project**

\# Su console.firebase.google.com:

\# 1. Create Project → \"creator-ai-proxy\"

\# 2. Enable services: Firestore, Cloud Functions, Secret Manager

\# 3. Seleziona regione: europe-west1

\# Output: Project ID (es: creator-ai-proxy-abc123)

**Step 2: Setup Local Development Environment**

\# Nel tuo computer

node \--version \# Richiesto: 18+

npm install -g firebase-tools

firebase login

firebase init functions \--project creator-ai-proxy-abc123

\# Scegli:

\# - Language: TypeScript (NOT JavaScript)

\# - ESLint: Yes

\# - Install dependencies: Yes

\# Output: Cartella functions/ con struttura

**Step 3: Struttura Repository**

creator-ai-proxy/

├── functions/

│ ├── src/

│ │ ├── index.ts \# Entry point

│ │ ├── types/

│ │ │ ├── License.ts

│ │ │ ├── Job.ts

│ │ │ └── APIResponse.ts

│ │ ├── lib/

│ │ │ ├── firestore.ts \# DB helpers

│ │ │ ├── secrets.ts \# Load API keys

│ │ │ └── logger.ts \# Structured logging

│ │ ├── api/

│ │ │ ├── auth/

│ │ │ │ └── validateLicense.ts

│ │ │ ├── ai/

│ │ │ │ └── routeRequest.ts

│ │ │ └── tasks/

│ │ │ ├── submitTask.ts

│ │ │ └── getStatus.ts

│ │ ├── providers/

│ │ │ ├── openai.ts

│ │ │ ├── gemini.ts

│ │ │ └── claude.ts

│ │ ├── services/

│ │ │ ├── aiRouter.ts

│ │ │ ├── licensing.ts

│ │ │ └── costCalculator.ts

│ │ └── middleware/

│ │ ├── auth.ts

│ │ └── rateLimit.ts

│ ├── tests/

│ │ ├── unit/

│ │ └── integration/

│ ├── .env.local (⚠️ .gitignore!)

│ ├── package.json

│ ├── tsconfig.json

│ └── firebase.json

├── .github/

│ └── workflows/

│ └── deploy.yml \# CI/CD

├── .gitignore

├── README.md

└── LICENSE

**Step 4: Configurare Firestore Collections**

Firestore Database Structure:

📁 licenses

└── doc: {license_key}

├── license_key: string

├── site_url: string

├── site_token: string (JWT)

├── plan: string (\'starter\'\|\'pro\'\|\'enterprise\')

├── tokens_limit: number

├── tokens_used: number

├── status: string (\'active\'\|\'suspended\'\|\'expired\')

├── reset_date: timestamp

├── expires_at: timestamp

├── created_at: timestamp

└── updated_at: timestamp

📁 audit_logs

└── doc: auto-generated

├── license_id: reference

├── request_type: string (\'TEXT_GEN\'\|\'CODE_GEN\'\|\'DESIGN_GEN\')

├── provider_used: string (\'openai\'\|\'gemini\'\|\'claude\')

├── tokens_input: number

├── tokens_output: number

├── cost_usd: number

├── status: string (\'success\'\|\'failed\'\|\'timeout\')

├── error_message: string

├── response_time_ms: number

└── created_at: timestamp

📁 job_queue

└── doc: {job_id}

├── job_id: string (UUID)

├── license_id: reference

├── task_type: string
(\'bulk_articles\'\|\'bulk_products\'\|\'design_batch\')

├── task_data: JSON

├── status: string
(\'pending\'\|\'processing\'\|\'completed\'\|\'failed\')

├── result: JSON

├── error_message: string

├── attempts: number (0-3)

├── created_at: timestamp

├── started_at: timestamp

└── completed_at: timestamp

📁 cost_tracking

└── doc: {license_id}\_{YYYY-MM}

├── license_id: reference

├── month: date

├── openai_tokens_input: number

├── openai_tokens_output: number

├── openai_cost_usd: number

├── gemini_tokens_input: number

├── gemini_tokens_output: number

├── gemini_cost_usd: number

├── claude_tokens_input: number

├── claude_tokens_output: number

├── claude_cost_usd: number

└── total_cost_usd: number

**Step 5: Setup Firebase Secrets**

\# Una volta (in locale, tu esegui questo)

cd functions

\# Carica le API keys in Firebase Secrets Manager

firebase functions:secrets:set GEMINI_API_KEY

firebase functions:secrets:set OPENAI_API_KEY

firebase functions:secrets:set CLAUDE_API_KEY

\# Output: Secrets caricati su Firebase (crittografati)

\# ⚠️ Non sono mai visibili in plain text

**Step 6: Setup GitHub Actions CI/CD**

\# .github/workflows/deploy.yml

name: Deploy to Firebase Functions

on:

push:

branches: \[main\]

jobs:

deploy:

runs-on: ubuntu-latest

steps:

\- uses: actions/checkout@v3

\- name: Setup Node.js

uses: actions/setup-node@v3

with:

node-version: \'18\'

\- name: Install dependencies

run: npm ci \--prefix functions

\- name: Run tests

run: npm run test \--prefix functions

\- name: Deploy to Firebase

uses: FirebaseExtended/action-hosting-deploy@v0

with:

repoToken: \${{ secrets.GITHUB_TOKEN }}

firebaseServiceAccount: \${{ secrets.FIREBASE_SERVICE_ACCOUNT }}

projectId: creator-ai-proxy-abc123

### Deliverables

-   ✅ Firebase project configurato (Firestore + Functions)

-   ✅ Repository GitHub con struttura TypeScript

-   ✅ Firestore collections e indexes creati

-   ✅ API keys caricate in Firebase Secrets (crittografate)

-   ✅ CI/CD pipeline configurata

-   ✅ .gitignore include .env.local

### Indicazioni per Claude Code

> \"Crea la struttura TypeScript completa per functions/src/ con:

-   types/ cartella con interfaces (License, Job, APIResponse, etc.)

-   lib/ cartella con helper functions (firestore queries, secrets
    > loading)

-   middleware/ con autenticazione e rate limiting

-   Entry point index.ts che importa tutte le funzioni

-   Tutti i file devono avere JSDoc comments

-   Nessun hardcoding di secrets (leggi da Firebase Secrets)\"

## 🔐 MILESTONE 2: Authentication & Licensing System

### Obiettivo

Implementare il sistema di autenticazione completo:

-   Validazione license key

-   Generazione JWT site_token

-   Gestione scadenze e status

-   Rate limiting

### Endpoint: POST /api/auth/validate-license

**Richiesta:**

{

\"license_key\": \"CREATOR-2024-XXXXX-XXXXX\",

\"site_url\": \"https://mysite.com\"

}

**Risposta (Success - 200):**

{

\"success\": true,

\"user_id\": \"user_123\",

\"site_token\": \"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9\...\",

\"plan\": \"pro\",

\"tokens_limit\": 50000000,

\"tokens_remaining\": 47654322,

\"reset_date\": \"2025-12-01\"

}

**Risposta (Error - 4xx/5xx):**

{

\"success\": false,

\"error\": \"License expired\",

\"code\": \"LICENSE_EXPIRED\"

}

### Logica Implementativa

1\. Validare formato license_key (regex)

\- Deve matchare: CREATOR-YYYY-XXXXX-XXXXX

2\. Query Firestore: licenses collection

WHERE license_key = incoming_key

3\. Se non trovato:

→ Respondere 404 (License not found)

4\. Se trovato, controllare:

\- status != \'active\' → 403 (suspended/expired)

\- expires_at \< now() → 403 (License expired)

\- site_url != incoming_url → 403 (URL mismatch - prevent sharing)

5\. Se valida:

\- Se site_token non esiste: Generare JWT

\- Calcolare tokens_remaining = tokens_limit - tokens_used

\- Log audit: \'license_validation_success\'

\- Ritornare response success

6\. Rate Limiting:

\- Max 10 richieste per IP per minuto

\- Se superato: 429 (Too Many Requests)

### Type Definitions

// types/License.ts

interface License {

license_key: string;

site_url: string;

site_token: string;

user_id: string;

plan: \'starter\' \| \'pro\' \| \'enterprise\';

tokens_limit: number;

tokens_used: number;

status: \'active\' \| \'suspended\' \| \'expired\';

reset_date: Date;

expires_at: Date;

created_at: Date;

updated_at: Date;

}

interface ValidateLicenseRequest {

license_key: string;

site_url: string;

}

interface ValidateLicenseResponse {

success: boolean;

user_id?: string;

site_token?: string;

plan?: \'starter\' \| \'pro\' \| \'enterprise\';

tokens_limit?: number;

tokens_remaining?: number;

reset_date?: Date;

error?: string;

code?: string;

}

### Deliverables

-   ✅ Endpoint /api/auth/validate-license operativo

-   ✅ JWT generation e validation

-   ✅ Rate limiting implementation

-   ✅ Audit logging completo

-   ✅ Error handling con codici specifici

-   ✅ Unit tests per tutti gli scenari

### Indicazioni per Claude Code

> \"Implementa api/auth/validateLicense.ts:

-   Usa Firebase Admin SDK per query Firestore

-   Generi JWT site_token (expire: 24h)

-   Rate limiting: redis o Firestore (counter based)

-   Logging: ogni tentativo (success/failed) con IP, timestamp

-   Testa: valid license, expired, wrong URL, rate limit exceeded\"

## 🤖 MILESTONE 3: AI Provider Integration

### Obiettivo

Integrare tre provider AI con:

-   Client classes per OpenAI, Gemini, Claude

-   Token counting accurato

-   Error handling e retry logic

-   Costo per token calcolato

### Provider Configuration

**OpenAI:**

\- Model: gpt-4o

\- Endpoint: https://api.openai.com/v1/chat/completions

\- Pricing: Input \$0.005/1K, Output \$0.015/1K

\- API Key: Via Firebase Secrets (OPENAI_API_KEY)

**Google Gemini:**

\- Model: gemini-1.5-flash (fast), gemini-1.5-pro (powerful)

\- Endpoint: https://generativelanguage.googleapis.com/v1beta/models

\- Pricing: Input \$0.075/1M, Output \$0.30/1M (flash)

\- API Key: Via Firebase Secrets (GEMINI_API_KEY)

**Anthropic Claude:**

\- Model: claude-3-5-sonnet-20241022

\- Endpoint: https://api.anthropic.com/v1/messages

\- Pricing: Input \$0.003/1K, Output \$0.015/1K

\- API Key: Via Firebase Secrets (CLAUDE_API_KEY)

### Class Interface (Tutti devono implementare)

interface IAIProvider {

generate(prompt: string, options?: GenerateOptions):
Promise\<AIResponse\>;

countTokens(text: string): Promise\<number\>;

getModel(): string;

getProviderName(): \'openai\' \| \'gemini\' \| \'claude\';

}

interface GenerateOptions {

model?: string;

temperature?: number; // 0-1

max_tokens?: number; // max output

system_prompt?: string; // system instruction

}

interface AIResponse {

success: boolean;

provider: string;

model: string;

content: string;

tokens_input: number;

tokens_output: number;

total_tokens: number;

cost_usd: number;

latency_ms: number;

}

### Error Handling Strategy

Rate Limited (429)

→ Retry after exponential backoff

→ Max 3 retries

→ Se tutte falliscono: Fallback a altro provider

Invalid API Key (401)

→ Log ERROR (security issue)

→ Non ritentare

→ Fallback a altro provider

Timeout (\>30s)

→ Considerare fallito

→ Fallback a altro provider

All Providers Down

→ Return 503 (Service Unavailable)

→ Alert admin

### Deliverables

-   ✅ OpenAI client (with retry logic)

-   ✅ Gemini client (with retry logic)

-   ✅ Claude client (with retry logic)

-   ✅ Token counting per provider

-   ✅ Cost calculation per provider

-   ✅ Error handling robusto

-   ✅ Unit tests per ogni provider

### Indicazioni per Claude Code

> \"Crea providers/openai.ts, providers/gemini.ts, providers/claude.ts:

-   Implementa interface IAIProvider

-   Carica API key da Firebase Secrets al runtime

-   Token counting accurato (usando le API ufficiali)

-   Retry logic con exponential backoff (3 tentativi)

-   Costo calcolato basato su token reali

-   JSDoc comments dettagliati

-   Test: valid generation, rate limit, timeout, invalid key\"

## 🎯 MILESTONE 4: Smart Router & Request Routing

### Obiettivo

Implementare logica intelligente di routing che decide quale provider
usare in base al task type e alle condizioni:

### Routing Matrix

TEXT_GEN (articoli, descrizioni)

→ Primario: Gemini 1.5 Flash (veloce + economico)

→ Fallback: OpenAI GPT-4o mini (se Gemini down)

→ Fallback finale: Claude (se entrambi down)

CODE_GEN (codice, configurazioni)

→ Primario: Claude 3.5 Sonnet (miglior codice)

→ Fallback: OpenAI GPT-4o (buon codice)

→ Fallback finale: Gemini Pro (se entrambi down)

DESIGN_GEN (layout, design systems)

→ Primario: Gemini 1.5 Pro (grande context window)

→ Fallback: OpenAI GPT-4o (se Gemini down)

→ Fallback finale: Claude (se entrambi down)

ECOMMERCE_GEN (prodotti, descrizioni lunghe)

→ Primario: Gemini 1.5 Pro (context window grande)

→ Fallback: OpenAI GPT-4o (se Gemini down)

→ Fallback finale: Claude (se entrambi down)

### Endpoint: POST /api/ai/route-request

**Richiesta:**

{

\"task_type\": \"TEXT_GEN\",

\"prompt\": \"Scrivi un articolo sui benefici del SEO\",

\"context\": {

\"site_title\": \"My Blog\",

\"theme\": \"twentythree\",

\"plugins\": \[\"elementor\", \"woocommerce\"\]

}

}

**Header:**

Authorization: Bearer {site_token}

**Risposta (Success - 200):**

{

\"success\": true,

\"content\": \"Generated article HTML\...\",

\"provider\": \"gemini\",

\"model\": \"gemini-1.5-flash\",

\"tokens_used\": 1250,

\"cost_usd\": 0.0942,

\"latency_ms\": 2341

}

### Logica Implementativa

1\. Autenticazione

\- Extract site_token from Authorization header

\- Verify JWT validity

\- Query Firestore: get license by site_token

\- Check license status (active) e quota (tokens_used \< tokens_limit)

2\. Validazione Richiesta

\- task_type deve essere uno di: TEXT_GEN, CODE_GEN, DESIGN_GEN,
ECOMMERCE_GEN

\- prompt non vuoto e \<10000 caratteri

\- Sanitizzazione: remove script tags, etc.

3\. Quota Check

\- tokens_remaining = tokens_limit - tokens_used

\- Se tokens_remaining \< 1000: Warning (low quota)

\- Se tokens_remaining \< 100: Error (quota exceeded)

4\. Provider Selection

\- Basato su task_type (routing matrix sopra)

\- Seleziona primario

\- Richiama: await primaryProvider.generate(prompt, options)

\- Se fallisce: Tenta fallback 1

\- Se fallisce: Tenta fallback 2

\- Se tutte falliscono: Return 503

5\. Cost Calculation

\- Ottieni tokens_input, tokens_output da provider

\- Calcola cost_usd usando provider pricing

\- Es: OpenAI = (tokens_input \* 0.005 + tokens_output \* 0.015) / 1000

6\. Update Firestore

\- licenses: tokens_used += tokens_input + tokens_output

\- audit_logs: insert new log entry

\- cost_tracking: add tokens e cost al mese corrente

7\. Return Response

\- Content: risultato generazione

\- Metadata: provider, model, tokens, cost, latency

### Type Definitions

type TaskType = \'TEXT_GEN\' \| \'CODE_GEN\' \| \'DESIGN_GEN\' \|
\'ECOMMERCE_GEN\';

interface RouteRequest {

task_type: TaskType;

prompt: string;

context?: Record\<string, any\>;

}

interface RouteResponse {

success: boolean;

content?: string;

provider?: string;

model?: string;

tokens_used?: number;

cost_usd?: number;

latency_ms?: number;

error?: string;

code?: string;

}

### Deliverables

-   ✅ Endpoint /api/ai/route-request operativo

-   ✅ Smart routing logic con fallback

-   ✅ Quota management completo

-   ✅ Cost tracking real-time

-   ✅ Audit logging per ogni richiesta

-   ✅ Error handling robusto

-   ✅ Performance monitoring (latency)

### Indicazioni per Claude Code

> \"Implementa api/ai/routeRequest.ts:

-   Autentica via site_token

-   Implementa routing matrix (vedi sopra)

-   Gestisci retry con fallback tra provider

-   Aggiorna Firestore (licenses, audit_logs, cost_tracking)

-   Rate limiting per license (max 100 req/min)

-   Testa: valid request, quota exceeded, provider down, rate limited\"

## 📦 MILESTONE 5: Async Job Queue & Task Processing

### Obiettivo

Implementare sistema per task lunghi (bulk operations):

-   Accettare richieste lunghe (bulk articles, bulk products)

-   Accodarle in job queue

-   Processarle in background

-   Permitere polling dello status

### Endpoint: POST /api/tasks/submit

**Richiesta:**

{

\"task_type\": \"bulk_articles\",

\"task_data\": {

\"topics\": \[

\"SEO Best Practices\",

\"WordPress Security\",

\"Performance Optimization\"

\],

\"tone\": \"professional\",

\"language\": \"it\"

}

}

**Risposta (202 Accepted):**

{

\"success\": true,

\"job_id\": \"job_f47ac10b-58cc-4372-a567-0e02b2c3d479\",

\"status\": \"pending\",

\"estimated_wait_seconds\": 45

}

### Endpoint: GET /api/tasks/status/:job_id

**Risposta (Pending - 200):**

{

\"success\": true,

\"job_id\": \"job\_\...\",

\"status\": \"processing\",

\"progress\": 66,

\"created_at\": \"2025-11-24T19:30:00Z\",

\"started_at\": \"2025-11-24T19:30:05Z\"

}

**Risposta (Completed - 200):**

{

\"success\": true,

\"job_id\": \"job\_\...\",

\"status\": \"completed\",

\"progress\": 100,

\"result\": {

\"articles\": \[

{

\"title\": \"SEO Best Practices\",

\"content\": \"\...\",

\"tokens_used\": 1250,

\"cost\": 0.0942

},

\...

\],

\"total_tokens\": 3750,

\"total_cost\": 0.2826

},

\"completed_at\": \"2025-11-24T19:31:00Z\"

}

**Risposta (Failed - 200):**

{

\"success\": true,

\"job_id\": \"job\_\...\",

\"status\": \"failed\",

\"error\": \"Quota exceeded during processing\",

\"completed_at\": \"2025-11-24T19:31:00Z\"

}

### Job Lifecycle

1\. POST /tasks/submit

→ Crea doc in job_queue collection

→ status: \'pending\'

→ Aggiunge a message queue (Cloud Tasks)

→ Return job_id + 202 Accepted

2\. Cloud Task Processor (background worker)

→ Legge job da queue

→ Setta status: \'processing\'

→ Per ogni item nel task_data:

\- Richiama /api/ai/route-request

\- Accumula result

\- Aggiorna progress

→ Se successo: status: \'completed\', salva result

→ Se fallisce: status: \'failed\', salva error

→ Aggiorna license tokens_used

3\. GET /tasks/status/:job_id

→ Legge doc da job_queue

→ Ritorna current status + result/error

### Task Types Supportati

\'bulk_articles\': Genera N articoli da lista di topic

Input: topics\[\], tone, language, length

Output: articles\[\] con title, content, tokens_used

\'bulk_products\': Genera N descrizioni prodotto

Input: products\[\], category, language

Output: products\[\] con title, short_desc, long_desc, cost

\'design_batch\': Genera N design sezioni Elementor

Input: sections\[\], style, theme

Output: sections\[\] con JSON elementor, cost

### Deliverables

-   ✅ Endpoint /api/tasks/submit operativo

-   ✅ Endpoint /api/tasks/status/:id operativo

-   ✅ Cloud Tasks integration (message queue)

-   ✅ Background job processor

-   ✅ Progress tracking

-   ✅ Error handling e retry logic

-   ✅ Timeout management (max 10 min per job)

### Indicazioni per Claude Code

> \"Implementa api/tasks/submitTask.ts e api/tasks/getStatus.ts:

-   POST: Autentica, valida task_data, crea Firestore doc, enqueue in
    > Cloud Tasks

-   GET: Leggi status da Firestore, ritorna progress + result/error

-   Background worker: Processa task asyncronamente, aggiorna progress,
    > cattura errori

-   Testa: valid submit, status polling, timeout, quota exceeded
    > mid-job\"

## 📊 MILESTONE 6: Analytics & Cost Tracking

### Obiettivo

Implementare tracking completo di:

-   Consumo token per provider

-   Costi reali per licenza

-   Metriche di utilizzo

### Collections Update

**cost_tracking document:**

{

license_id: \"ref\",

month: \"2025-11\",

openai_tokens_input: 145000,

openai_tokens_output: 89000,

openai_cost_usd: 1.245,

gemini_tokens_input: 234000,

gemini_tokens_output: 156000,

gemini_cost_usd: 0.758,

claude_tokens_input: 67000,

claude_tokens_output: 42000,

claude_cost_usd: 0.342,

total_cost_usd: 2.345

}

### Update Strategy

1\. Dopo ogni richiesta AI:

\- Query: cost_tracking WHERE license_id = X AND month = current_month

\- Se non esiste: Crea nuovo doc

\- Aggiorna:

\- {provider}\_tokens_input += tokens_input

\- {provider}\_tokens_output += tokens_output

\- {provider}\_cost_usd += costo calcolato

\- total_cost_usd = sum of all provider costs

2\. Dopo ogni job completato:

\- Aggrega tutti i token/costo dal job

\- Aggiorna cost_tracking come sopra

### Endpoint: GET /api/analytics (futuro)

Opzionale per questa phase, ma documentare formato:

{

\"period\": \"2025-11\",

\"total_requests\": 342,

\"total_tokens\": 635000,

\"total_cost\": 2.345,

\"breakdown_by_provider\": {

\"openai\": {\"tokens\": 234000, \"cost\": 1.245},

\"gemini\": {\"tokens\": 390000, \"cost\": 0.758},

\"claude\": {\"tokens\": 109000, \"cost\": 0.342}

},

\"breakdown_by_task\": {

\"TEXT_GEN\": {\"requests\": 180, \"tokens\": 245000, \"cost\": 0.934},

\"CODE_GEN\": {\"requests\": 98, \"tokens\": 234000, \"cost\": 0.856},

\"DESIGN_GEN\": {\"requests\": 64, \"tokens\": 156000, \"cost\": 0.555}

}

}

### Deliverables

-   ✅ cost_tracking collection gestita correttamente

-   ✅ Aggiornamenti real-time dopo ogni richiesta

-   ✅ Costi per provider accurati

-   ✅ Dashboard-ready data format

## 🚀 MILESTONE 7: Deployment & Monitoring

### Obiettivo

Deployare proxy in production con:

-   Zero downtime deployment

-   Monitoring completo

-   Alerting per anomalie

### Pre-Deployment Checklist

□ Tutti i test passano (unit + integration)

□ Environment variables configurate (Firebase Secrets)

□ Firestore indexes creati

□ Rate limiting testato

□ Load testing completato (simulare 100 req/s)

□ Security audit passato (OWASP Top 10)

□ Documentation completata (API docs, runbooks)

□ GitHub Actions CI/CD funzionante

### Deployment Command

firebase deploy \--only functions \--project creator-ai-proxy-abc123

### Monitoring Setup

Metrics da tracciare:

\- Request count (per endpoint)

\- Response latency (P50, P95, P99)

\- Error rate (per status code)

\- Provider health (uptime %)

\- Token consumption (per license)

\- Cost tracking accuracy

Alert Conditions:

\- Error rate \> 5% → Warn

\- Latency P95 \> 5s → Warn

\- Any provider error rate \> 20% → Critical

\- Quota exceeded errors \> 50/min → Critical

### Deliverables

-   ✅ Proxy live su Firebase

-   ✅ Monitoring dashboard funzionante

-   ✅ Alerting configurato

-   ✅ Runbook per common issues

-   ✅ API documentation (Swagger/OpenAPI)

## 📝 Code Quality Standards

Tutte le funzioni DEVONO avere:

/\*\*

\* Descrizione breve della funzione

\*

\* \@param {type} paramName - Descrizione del parametro

\* \@returns {type} Descrizione del return value

\* \@throws {ErrorType} Quando e perché lancia errore

\*

\* \@example

\* \`\`\`typescript

\* const result = await myFunction(input);

\* \`\`\`

\*/

async function myFunction(param: string): Promise\<Result\> {

// Implementation

}

### Errori Standard

interface APIError {

success: false;

error: string; // Messaggio user-friendly

code: string; // Interno (LICENSE_EXPIRED, QUOTA_EXCEEDED, etc.)

status: number; // HTTP status

details?: any; // Debug info (solo in development)

}

### Logging Standard

logger.info(\'License validated\', {

license_id: \'lic_123\',

site_url: \'https://example.com\',

plan: \'pro\'

});

logger.error(\'Provider error\', {

provider: \'openai\',

error: \'Rate limited\',

job_id: \'job_123\'

});

## 📋 Summary per Claude Code e Gemini

**Avrete completa autonomia nel generare il codice di ogni milestone
seguendo:**

1.  ✅ TypeScript (non JavaScript)

2.  ✅ Firebase Admin SDK per Firestore

3.  ✅ Carica secrets da Firebase Secrets (MAI in plain text)

4.  ✅ JSDoc comments su ogni funzione

5.  ✅ Error handling con codici specifici

6.  ✅ Unit tests per ogni funzione

7.  ✅ Type safety (interfaces, generics)

8.  ✅ Structured logging

9.  ✅ Seguite le routing matrix e cost calculation specs

**Ogni milestone è indipendente e può essere generata sequenzialmente.**

**Non dovete comunicare tra voi - ogni funzione è self-contained e
testabile.**

## 🔒 Security Considerations

✅ **NON fate questi errori:**

-   ❌ Hardcode API keys

-   ❌ Loggate API keys

-   ❌ Salvate passwords in plain text

-   ❌ Fidate di input senza validazione

-   ❌ Eseguite SQL queries senza prepared statements

✅ **FATE questi:**

-   ✅ Carica secrets da Firebase Secrets

-   ✅ Valida e sanitizza tutti gli input

-   ✅ Rate limiting su tutti gli endpoint

-   ✅ Audit logging per azioni critiche

-   ✅ JWT con expiration time

**Documento Completo - Pronto per Claude Code e Gemini**
