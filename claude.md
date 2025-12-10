# Creator MVP - Roadmap e Specifiche Tecniche

**Versione**: 3.0.0-MVP  
**Data**: Dicembre 2025  
**Stato**: Da Implementare

---

## Indice

1. [Cos'è Creator](#cosè-creator)
2. [Obiettivi MVP](#obiettivi-mvp)
3. [Principi Fondamentali](#principi-fondamentali)
4. [Architettura del Sistema](#architettura-del-sistema)
5. [Cosa Tenere e Cosa Eliminare](#cosa-tenere-e-cosa-eliminare)
6. [Struttura File - WordPress Plugin](#struttura-file---wordpress-plugin)
7. [Struttura File - Firebase Functions](#struttura-file---firebase-functions)
8. [Formato Comunicazione AI](#formato-comunicazione-ai)
9. [Flusso di Orchestrazione (4 Step)](#flusso-di-orchestrazione-4-step)
10. [Sistema Documentazione Plugin](#sistema-documentazione-plugin)
11. [System Prompt AI](#system-prompt-ai)
12. [Specifiche Componenti WordPress](#specifiche-componenti-wordpress)
13. [Specifiche Componenti Firebase](#specifiche-componenti-firebase)
14. [Piano di Sviluppo](#piano-di-sviluppo)
15. [Test di Validazione](#test-di-validazione)

---

## Cos'è Creator

### La Missione

**Creator** è un plugin WordPress alimentato da intelligenza artificiale progettato per **sostituire un'intera agenzia di sviluppo e gestione siti web WordPress**.

Non è un semplice chatbot che risponde a domande. Creator è un sistema operativo AI completo che:

1. **Comprende** richieste complesse in linguaggio naturale
2. **Pianifica** strategie di implementazione
3. **Esegue** direttamente modifiche sul sito WordPress
4. **Verifica** che le modifiche siano state applicate correttamente
5. **Corregge** eventuali errori autonomamente

### Cosa Può Fare Creator

Creator può eseguire **qualsiasi operazione** che un esperto WordPress potrebbe fare:

| Categoria | Esempi di Operazioni |
|-----------|---------------------|
| **Contenuti** | Creare pagine, articoli, prodotti WooCommerce |
| **Design** | Costruire layout completi con Elementor, stilizzare con CSS |
| **Configurazione** | Settare plugin di cache, SEO, sicurezza |
| **Sviluppo** | Scrivere snippet PHP, modificare functions.php |
| **Manutenzione** | Aggiornare plugin, ottimizzare database |
| **E-commerce** | Configurare WooCommerce, creare prodotti, gestire ordini |

### Esempio di Task Complesso

Un utente può chiedere:

```
"Crea la homepage del sito usando Elementor con:
- Hero Section con video background
- Sezione Features a 3 colonne
- Testimonials carousel
- Pricing table
- FAQ accordion
- Footer con form contatto"
```

Creator analizzerà la richiesta, creerà un piano, eseguirà ogni sezione una alla volta, e verificherà il risultato finale.

### Caratteristica Chiave: Nessun Limite Hardcoded

Creator **NON** ha azioni predefinite. Non esiste una lista di "cose che può fare".

L'AI genera direttamente **codice PHP** che viene eseguito su WordPress. Questo significa:

- Nessun limite alle operazioni possibili
- Compatibilità con qualsiasi plugin (presente e futuro)
- Adattamento automatico all'ecosistema dell'utente

---

## Obiettivi MVP

### Obiettivo Primario

Creare un sistema funzionante end-to-end dove:

1. L'utente scrive una richiesta nella chat
2. Creator comprende, pianifica, esegue
3. Le modifiche appaiono su WordPress

### Obiettivi Specifici

| # | Obiettivo | Priorità |
|---|-----------|----------|
| 1 | Chat funzionante con risposta AI | CRITICO |
| 2 | Esecuzione codice PHP su WordPress | CRITICO |
| 3 | Loop multi-step per task complessi | CRITICO |
| 4 | Recupero documentazione plugin on-demand | ALTO |
| 5 | Debug conversazione visibile | MEDIO |
| 6 | Status log nella chat | MEDIO |

### Non-Obiettivi MVP (Esclusi)

- Sistema di backup/rollback
- Integrazioni hardcoded con plugin specifici
- Job queue per task asincroni
- Analytics dettagliati
- Rate limiting sofisticato
- Security hardening
- Gestione multi-utente

---

## Principi Fondamentali

### 1. Soluzione B: PHP Puro

L'AI genera codice PHP che viene eseguito direttamente via `eval()`.

```
UTENTE: "Crea una pagina chiamata Test"

AI GENERA:
{
  "type": "execute",
  "code": "$page_id = wp_insert_post(['post_title' => 'Test', 'post_type' => 'page', 'post_status' => 'publish']); return ['success' => true, 'page_id' => $page_id];"
}

PLUGIN ESEGUE:
eval($code);

RISULTATO:
Pagina creata su WordPress
```

### 2. Nessuna Integrazione Hardcoded

Creator non sa "nativamente" come usare Elementor o WooCommerce. Impara leggendo la documentazione.

```
SBAGLIATO: ElementorIntegration.php con metodi specifici
CORRETTO: L'AI legge la doc Elementor e genera il codice appropriato
```

### 3. Documentazione On-Demand

L'AI riceve sempre la lista dei plugin installati. Quando serve, chiede la documentazione specifica.

```
CONTESTO BASE (sempre presente):
- WordPress 6.4.2, PHP 8.2, MySQL 8.0
- Tema: flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor 2.1.0
- Plugin: Elementor 3.18, WooCommerce 8.4, RankMath 1.0.2...

DOCUMENTAZIONE (richiesta quando serve):
"Ho bisogno della documentazione di Elementor per creare la pagina"
```

### 4. Loop di Orchestrazione

Ogni task segue 4 step:

1. **Discovery**: Comprensione della richiesta
2. **Strategy**: Pianificazione delle azioni
3. **Implementation**: Esecuzione del codice
4. **Verification**: Verifica del risultato

---

## Architettura del Sistema

```
┌─────────────────────────────────────────────────────────────────────┐
│                         WORDPRESS PLUGIN                             │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                     Chat Interface                            │   │
│  │  - Input messaggio utente                                     │   │
│  │  - Display conversazione                                      │   │
│  │  - Status log ("Thinking...", "Executing 1/3...")             │   │
│  │  - Debug panel (collapsabile)                                 │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                              │                                       │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │                    Core Components                            │   │
│  │                                                                │   │
│  │  ContextLoader ──────→ Raccoglie info WP, tema, plugin       │   │
│  │                                                                │   │
│  │  ProxyClient ────────→ Comunicazione con Firebase             │   │
│  │                                                                │   │
│  │  ResponseHandler ────→ Parsa risposta AI, decide azione       │   │
│  │                                                                │   │
│  │  CodeExecutor ───────→ Esegue PHP via eval()                  │   │
│  │                                                                │   │
│  │  ConversationManager → Gestisce history e loop multi-step    │   │
│  │                                                                │   │
│  │  DebugLogger ────────→ Log conversazione raw                  │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              │ HTTPS + JWT
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         FIREBASE FUNCTIONS                           │
│                                                                      │
│  validateLicense ───→ Valida licenza, genera JWT                    │
│                                                                      │
│  routeRequest ──────→ Riceve prompt + context                       │
│                       Costruisce system prompt                       │
│                       Allega documentazione (se richiesta)          │
│                       Chiama AI (Gemini/Claude)                     │
│                       Ritorna risposta JSON                         │
│                                                                      │
│  pluginDocs ────────→ Repository documentazione plugin              │
│                       Cerca/salva documentazione                     │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────────┐
                    │     FIRESTORE       │
                    │   - licenses        │
                    │   - plugin_docs     │
                    └─────────────────────┘
```

---

## Cosa Tenere e Cosa Eliminare

### Firebase - DA TENERE ✅

| File/Componente | Stato | Note |
|-----------------|-------|------|
| `api/auth/validateLicense.ts` | ✅ Tenere | Funziona, non toccare |
| `api/ai/routeRequest.ts` | ✅ Semplificare | Rimuovere logica extra |
| `api/plugin-docs/*` | ✅ Tenere | Repository documentazione |
| `services/licensing.ts` | ✅ Tenere | Funziona |
| `services/modelService.ts` | ✅ Semplificare | Solo generate() |
| `providers/gemini.ts` | ✅ Tenere | Pulire |
| `providers/claude.ts` | ✅ Tenere | Pulire |
| `middleware/auth.ts` | ✅ Tenere | JWT validation |
| `middleware/rateLimit.ts` | ✅ Semplificare | Solo in-memory |
| `lib/jwt.ts` | ✅ Tenere | Sign/verify |
| `lib/firestore.ts` | ✅ Semplificare | Solo licenses + plugin_docs |
| `lib/logger.ts` | ✅ Tenere | Logging |
| `config/models.ts` | ✅ Tenere | Model IDs |

### Firebase - DA ELIMINARE ❌

| File/Componente | Motivo |
|-----------------|--------|
| `api/analytics/*` | Non serve per MVP |
| `api/tasks/*` | Job queue non serve |
| `triggers/jobQueueTrigger.ts` | Non serve |
| `services/costCalculator.ts` | Non serve |
| `services/jobProcessor.ts` | Non serve |
| `services/aiRouter.ts` | Logica nel modelService |
| `providers/openai.ts` | Non utilizzato |
| Tutti i file relativi a "tier" | Sistema rimosso |

### WordPress Plugin - DA TENERE ✅

| Componente | Stato | Note |
|------------|-------|------|
| `creator-core.php` | ✅ Semplificare | Entry point |
| `Loader.php` | ✅ Semplificare | Bootstrap minimo |
| `ProxyClient.php` | ✅ Semplificare | Solo comunicazione |
| `ContextLoader.php` | ✅ Adattare | Raccolta info |
| Settings base | ✅ Semplificare | Solo license key |

### WordPress Plugin - DA ELIMINARE ❌

| Componente | Motivo |
|------------|--------|
| `SnapshotManager.php` | No backup/rollback |
| `DeltaBackup.php` | No backup/rollback |
| `Rollback.php` | No backup/rollback |
| `ElementorPageBuilder.php` | No integrazioni hardcoded |
| `ElementorSchemaLearner.php` | No integrazioni hardcoded |
| `ElementorIntegration.php` | No integrazioni hardcoded |
| `ElementorActionHandler.php` | No integrazioni hardcoded |
| `ACFIntegration.php` | No integrazioni hardcoded |
| `RankMathIntegration.php` | No integrazioni hardcoded |
| `WooCommerceIntegration.php` | No integrazioni hardcoded |
| `LiteSpeedIntegration.php` | No integrazioni hardcoded |
| `WPCodeIntegration.php` | No integrazioni hardcoded |
| `ActionDispatcher.php` (vecchio) | Logica azioni hardcoded |
| Tutti gli ActionHandler specifici | No azioni hardcoded |
| `SetupWizard.php` complesso | Troppo complesso |
| Sistema permessi complesso | Non per MVP |
| Rate limiting WP | Non per MVP |
| Audit logger complesso | Non per MVP |

---

## Struttura File - WordPress Plugin

```
creator-core/
├── creator-core.php                 # Entry point plugin
│
├── includes/
│   ├── Loader.php                   # Bootstrap e inizializzazione
│   │
│   ├── Admin/
│   │   └── Settings.php             # Pagina settings (license key, proxy URL)
│   │
│   ├── Chat/
│   │   ├── ChatInterface.php        # Registra pagina admin e assets
│   │   └── ChatController.php       # REST endpoint POST /creator/v1/chat
│   │
│   ├── Context/
│   │   └── ContextLoader.php        # Raccoglie info WP (tema, plugin, versioni)
│   │
│   ├── Conversation/
│   │   └── ConversationManager.php  # Gestisce history e loop multi-step
│   │
│   ├── Response/
│   │   └── ResponseHandler.php      # Parsa risposta AI, decide azione
│   │
│   ├── Executor/
│   │   └── CodeExecutor.php         # Esegue PHP (eval wrapper)
│   │
│   ├── Proxy/
│   │   └── ProxyClient.php          # Comunicazione con Firebase
│   │
│   └── Debug/
│       └── DebugLogger.php          # Log conversazione per debug
│
├── assets/
│   ├── js/
│   │   └── chat.js                  # UI chat + status updates
│   └── css/
│       └── chat.css                 # Stili chat
│
└── views/
    ├── chat.php                     # Template pagina chat
    └── settings.php                 # Template pagina settings

TOTALE: ~18 file
```

---

## Struttura File - Firebase Functions

```
functions/src/
├── index.ts                         # Entry point, esporta functions
│
├── api/
│   ├── auth/
│   │   └── validateLicense.ts       # POST /validate-license
│   │
│   ├── ai/
│   │   └── routeRequest.ts          # POST /route-request
│   │
│   └── plugin-docs/
│       ├── getPluginDocs.ts         # GET /plugin-docs/:slug
│       └── searchPluginDocs.ts      # POST /plugin-docs/search
│
├── services/
│   ├── licensing.ts                 # Validazione licenze
│   ├── modelService.ts              # Chiamate AI (Gemini/Claude)
│   └── pluginDocsService.ts         # Gestione repository doc
│
├── providers/
│   ├── gemini.ts                    # Google Gemini provider
│   └── claude.ts                    # Anthropic Claude provider
│
├── middleware/
│   ├── auth.ts                      # JWT authentication
│   └── rateLimit.ts                 # Rate limiting in-memory
│
├── lib/
│   ├── firestore.ts                 # Wrapper Firestore
│   ├── jwt.ts                       # Sign/verify JWT
│   ├── logger.ts                    # Structured logging
│   └── secrets.ts                   # Firebase Secrets
│
├── config/
│   └── models.ts                    # MODEL_IDS, configurazione
│
└── types/
    ├── AIProvider.ts                # Interface provider
    ├── Auth.ts                      # Types autenticazione
    ├── Route.ts                     # Types richieste
    └── PluginDocs.ts                # Types documentazione

TOTALE: ~20 file
```

---

## Formato Comunicazione AI

### Struttura Risposta JSON

L'AI risponde **SEMPRE** con questo formato JSON:

```json
{
  "step": "discovery | strategy | implementation | verification",
  "type": "question | plan | execute | verify | complete | error | request_docs",
  "status": "Stato breve per UI (es. 'Analyzing request...')",
  "message": "Messaggio completo da mostrare all'utente",
  "data": {},
  "requires_confirmation": false,
  "continue_automatically": true
}
```

### Campi

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `step` | string | Fase corrente del processo |
| `type` | string | Tipo di risposta |
| `status` | string | Messaggio breve per status log UI |
| `message` | string | Messaggio completo per l'utente |
| `data` | object | Dati specifici per tipo |
| `requires_confirmation` | boolean | Se true, attende conferma utente |
| `continue_automatically` | boolean | Se true, procede automaticamente |

### Tipi di Risposta

#### `type: "question"` - Domanda Chiarificatrice

```json
{
  "step": "discovery",
  "type": "question",
  "status": "Waiting for clarification...",
  "message": "Per creare la landing page ho bisogno di sapere:\n\n1. Vuoi usare Elementor o l'editor classico?\n2. Hai già i contenuti o li genero io?",
  "data": {},
  "requires_confirmation": false,
  "continue_automatically": false
}
```

#### `type: "plan"` - Piano d'Azione

```json
{
  "step": "strategy",
  "type": "plan",
  "status": "Plan ready",
  "message": "Ecco il piano per creare la homepage:",
  "data": {
    "actions": [
      { "index": 1, "description": "Creare pagina base con template Elementor Canvas" },
      { "index": 2, "description": "Costruire Hero Section con video background" },
      { "index": 3, "description": "Aggiungere Features Section (3 colonne)" }
    ],
    "total_actions": 3,
    "estimated_time": "2-3 minuti"
  },
  "requires_confirmation": true,
  "continue_automatically": false
}
```

#### `type: "execute"` - Esecuzione Codice

```json
{
  "step": "implementation",
  "type": "execute",
  "status": "Executing step 1/3...",
  "message": "Sto creando la pagina base...",
  "data": {
    "code": "$page_id = wp_insert_post(['post_title' => 'Homepage', 'post_type' => 'page', 'post_status' => 'draft', 'meta_input' => ['_elementor_edit_mode' => 'builder']]); return ['success' => true, 'page_id' => $page_id];",
    "action_index": 1,
    "action_total": 3,
    "action_description": "Creazione pagina base"
  },
  "requires_confirmation": false,
  "continue_automatically": true
}
```

#### `type: "verify"` - Verifica Risultato

```json
{
  "step": "verification",
  "type": "verify",
  "status": "Verifying...",
  "message": "Verifico che la pagina sia stata creata...",
  "data": {
    "code": "$page = get_post($context['last_result']['page_id']); return ['exists' => !empty($page), 'title' => $page->post_title, 'status' => $page->post_status];",
    "expected": {
      "exists": true
    }
  },
  "requires_confirmation": false,
  "continue_automatically": true
}
```

#### `type: "complete"` - Task Completato

```json
{
  "step": "verification",
  "type": "complete",
  "status": "Completed",
  "message": "✅ Homepage creata con successo!\n\nLa pagina è in bozza. Puoi trovarla qui: /wp-admin/post.php?post=123&action=edit\n\nVuoi che la pubblichi?",
  "data": {
    "summary": {
      "pages_created": 1,
      "sections_added": 3
    },
    "links": [
      { "label": "Modifica pagina", "url": "/wp-admin/post.php?post=123&action=edit" }
    ]
  },
  "requires_confirmation": false,
  "continue_automatically": false
}
```

#### `type: "error"` - Errore

```json
{
  "step": "implementation",
  "type": "error",
  "status": "Error occurred",
  "message": "Si è verificato un errore durante la creazione della pagina:\n\n`wp_insert_post` ha ritornato un errore: 'Invalid post type'",
  "data": {
    "error_code": "WP_INSERT_ERROR",
    "error_message": "Invalid post type",
    "recoverable": true,
    "suggestion": "Verifico se il post type 'page' è disponibile e riprovo con un approccio diverso."
  },
  "requires_confirmation": false,
  "continue_automatically": true
}
```

#### `type: "request_docs"` - Richiesta Documentazione

```json
{
  "step": "discovery",
  "type": "request_docs",
  "status": "Fetching documentation...",
  "message": "Recupero la documentazione necessaria...",
  "data": {
    "plugins_needed": ["elementor", "contact-form-7"],
    "reason": "Per creare la landing page con form di contatto"
  },
  "requires_confirmation": false,
  "continue_automatically": true
}
```

---

## Flusso di Orchestrazione (4 Step)

### Step 1: Discovery

**Obiettivo**: Comprendere esattamente cosa vuole l'utente.

**Comportamento AI**:
- Se la richiesta è chiara e completa → passa a Strategy
- Se la richiesta è ambigua → fa domande chiarificatrici
- Se servono informazioni su plugin → richiede documentazione

**Esempio**:
```
UTENTE: "Crea una landing page"

AI (Discovery):
"Per creare la landing page ho bisogno di alcune informazioni:
1. Vuoi usare Elementor o l'editor classico?
2. Quante sezioni deve avere?
3. Hai già i contenuti o li genero io?"
```

### Step 2: Strategy

**Obiettivo**: Creare un piano d'azione dettagliato.

**Comportamento AI**:
- Analizza la richiesta compresa
- Crea un piano con azioni numerate
- Chiede conferma all'utente (se task complesso)

**Esempio**:
```
UTENTE: "Usa Elementor, 5 sezioni, genera i contenuti tu"

AI (Strategy):
"Ecco il piano:
1. Creare pagina WordPress con template Elementor
2. Costruire Hero Section
3. Costruire Features Section
4. Costruire Testimonials Section
5. Costruire CTA Section
6. Costruire Footer Section

Procedo?"
```

### Step 3: Implementation

**Obiettivo**: Eseguire il piano, un'azione alla volta.

**Comportamento AI**:
- Genera codice PHP per ogni azione
- Aspetta il risultato dell'esecuzione
- Passa alla prossima azione o gestisce errori

**Esempio**:
```
AI (Implementation - Step 1/6):
{
  "type": "execute",
  "code": "// Codice PHP per creare la pagina",
  "action_index": 1,
  "action_total": 6
}

[Plugin esegue, ritorna risultato]

AI (Implementation - Step 2/6):
{
  "type": "execute", 
  "code": "// Codice PHP per Hero Section",
  "action_index": 2,
  "action_total": 6
}

[...continua...]
```

### Step 4: Verification

**Obiettivo**: Verificare che tutto sia stato eseguito correttamente.

**Comportamento AI**:
- Esegue controlli di verifica
- Se qualcosa non va → torna a Strategy con nuovo approccio
- Se tutto ok → mostra messaggio di completamento

**Esempio**:
```
AI (Verification):
{
  "type": "verify",
  "code": "// Codice per verificare che la pagina esista e abbia le sezioni"
}

[Plugin esegue verifica]

AI (Complete):
"✅ Landing page creata con successo!
- 5 sezioni aggiunte
- Pagina in bozza
- Link: /wp-admin/post.php?post=123

Vuoi che la pubblichi?"
```

---

## Sistema Documentazione Plugin

### Contesto Base (Sempre Inviato)

```json
{
  "wordpress": {
    "version": "6.4.2",
    "locale": "it_IT",
    "multisite": false
  },
  "php": {
    "version": "8.2.0"
  },
  "mysql": {
    "version": "8.0.35"
  },
  "theme": {
    "name": "flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor",
    "version": "2.1.0",
    "parent": null
  },
  "plugins": [
    {
      "name": "Elementor",
      "slug": "elementor",
      "version": "3.18.0",
      "active": true
    },
    {
      "name": "WooCommerce", 
      "slug": "woocommerce",
      "version": "8.4.0",
      "active": true
    },
    {
      "name": "Rank Math SEO",
      "slug": "seo-by-flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor-flavor-seo",
      "version": "1.0.208",
      "active": true
    },
    {
      "name": "Speed Optimizer",
      "slug": "sg-cachepress", 
      "version": "7.4.0",
      "active": true
    }
  ]
}
```

### Flusso Documentazione On-Demand

```
1. UTENTE: "Ottimizza la cache del sito"

2. AI vede la lista plugin, identifica Speed Optimizer

3. AI risponde:
   {
     "type": "request_docs",
     "data": {
       "plugins_needed": ["sg-cachepress"],
       "reason": "Configurazione cache"
     }
   }

4. PLUGIN:
   - Riceve richiesta
   - Chiama Firebase: GET /plugin-docs/sg-cachepress?version=7.4.0
   - Firebase cerca nel repository
   - Se non trovata: cerca online e salva
   - Ritorna documentazione

5. PLUGIN richiama AI con:
   {
     "prompt": "[messaggio originale]",
     "context": { ...contesto base... },
     "documentation": {
       "sg-cachepress": "Speed Optimizer Documentation v7.4\n\n## Options\n..."
     }
   }

6. AI continua normalmente con la documentazione disponibile
```

### Struttura Repository Firestore

```
plugin_docs/
├── elementor/
│   ├── 3.18.0: { doc: "...", fetched_at: "2024-12-10", source: "https://..." }
│   ├── 3.17.0: { doc: "...", fetched_at: "2024-11-15", source: "https://..." }
│   └── latest: "3.18.0"
├── woocommerce/
│   ├── 8.4.0: { doc: "...", fetched_at: "2024-12-08", source: "https://..." }
│   └── latest: "8.4.0"
└── sg-cachepress/
    ├── 7.4.0: { doc: "...", fetched_at: "2024-12-10", source: "https://..." }
    └── latest: "7.4.0"
```

---

## System Prompt AI

### Prompt Base

```
You are Creator, an AI-powered WordPress development assistant. Your role is to help users build, configure, and manage WordPress websites by generating and executing PHP code directly.

## CORE PRINCIPLES

1. **You can do ANYTHING a WordPress expert can do** - there are no hardcoded limits
2. **You generate PHP code** that gets executed on WordPress
3. **You learn from documentation** - you don't have built-in knowledge of specific plugins
4. **You follow a 4-step process**: Discovery → Strategy → Implementation → Verification

## RESPONSE FORMAT

You MUST respond with valid JSON in this exact format:

```json
{
  "step": "discovery | strategy | implementation | verification",
  "type": "question | plan | execute | verify | complete | error | request_docs",
  "status": "Short status for UI",
  "message": "Full message for user",
  "data": {},
  "requires_confirmation": false,
  "continue_automatically": true
}
```

## ORCHESTRATION PROCESS

### Step 1: Discovery
- If the request is clear and complete → proceed to Strategy
- If the request is ambiguous → ask clarifying questions (type: "question")
- If you need plugin documentation → request it (type: "request_docs")

### Step 2: Strategy
- Create a numbered action plan
- For complex tasks (3+ actions), ask for user confirmation
- Include estimated steps and time

### Step 3: Implementation
- Generate PHP code for each action
- Execute one action at a time
- Wait for result before proceeding
- Handle errors gracefully

### Step 4: Verification
- Verify the results match expectations
- If something failed → return to Strategy with alternative approach
- If all succeeded → show completion message

## CODE EXECUTION

When generating code (type: "execute"), the code will be executed via eval() in WordPress context.

**Available in scope:**
- All WordPress functions (wp_insert_post, get_posts, update_option, etc.)
- $wpdb for database queries
- All active plugin functions
- $context array with previous results

**Code requirements:**
- Must be valid PHP
- Must return a result array (success/failure + data)
- Should handle errors gracefully
- No <?php tags needed

**Example:**
```php
$page_id = wp_insert_post([
    'post_title' => 'My Page',
    'post_type' => 'page',
    'post_status' => 'publish'
]);

if (is_wp_error($page_id)) {
    return ['success' => false, 'error' => $page_id->get_error_message()];
}

return ['success' => true, 'page_id' => $page_id];
```

## DOCUMENTATION

You receive the WordPress context with:
- WordPress, PHP, MySQL versions
- Active theme and version
- List of installed plugins with versions

When you need to use a plugin's specific functions, request its documentation:
```json
{
  "type": "request_docs",
  "data": {
    "plugins_needed": ["plugin-slug"],
    "reason": "Why you need it"
  }
}
```

The documentation will be provided and you can continue.

## LANGUAGE

- Respond in the same language as the user's message
- Italian users → respond in Italian
- English users → respond in English

## IMPORTANT RULES

1. NEVER say "I can't do this" - you can do anything via PHP
2. NEVER make up plugin functions - request documentation if unsure
3. ALWAYS return valid JSON
4. ALWAYS include status for UI feedback
5. For multi-step tasks, execute ONE step at a time
6. If an error occurs, try an alternative approach before giving up
```

---

## Specifiche Componenti WordPress

### 1. creator-core.php

**Responsabilità**: Entry point del plugin

```php
<?php
/**
 * Plugin Name: Creator Core
 * Description: AI-powered WordPress development assistant
 * Version: 3.0.0
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

define('CREATOR_VERSION', '3.0.0');
define('CREATOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CREATOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'CreatorCore\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    
    $relative_class = substr($class, strlen($prefix));
    $file = CREATOR_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) require $file;
});

// Bootstrap
add_action('plugins_loaded', function() {
    $loader = new CreatorCore\Loader();
    $loader->init();
});
```

### 2. Loader.php

**Responsabilità**: Inizializzazione componenti

```php
<?php
namespace CreatorCore;

class Loader {
    public function init(): void {
        // Admin pages
        if (is_admin()) {
            new Admin\Settings();
            new Chat\ChatInterface();
        }
        
        // REST API
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes(): void {
        $chat_controller = new Chat\ChatController();
        $chat_controller->register_routes();
    }
}
```

### 3. ChatController.php

**Responsabilità**: REST endpoint per la chat

```php
<?php
namespace CreatorCore\Chat;

class ChatController {
    
    public function register_routes(): void {
        register_rest_route('creator/v1', '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_message'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }
    
    public function check_permissions(): bool {
        return current_user_can('manage_options');
    }
    
    public function handle_message(\WP_REST_Request $request): \WP_REST_Response {
        $message = sanitize_textarea_field($request->get_param('message'));
        $conversation_id = sanitize_text_field($request->get_param('conversation_id'));
        
        $conversation_manager = new \CreatorCore\Conversation\ConversationManager();
        $response = $conversation_manager->process_message($message, $conversation_id);
        
        return new \WP_REST_Response($response, 200);
    }
}
```

### 4. ConversationManager.php

**Responsabilità**: Gestione conversazione e loop multi-step

```php
<?php
namespace CreatorCore\Conversation;

class ConversationManager {
    
    private $proxy_client;
    private $response_handler;
    private $context_loader;
    private $debug_logger;
    
    public function __construct() {
        $this->proxy_client = new \CreatorCore\Proxy\ProxyClient();
        $this->response_handler = new \CreatorCore\Response\ResponseHandler();
        $this->context_loader = new \CreatorCore\Context\ContextLoader();
        $this->debug_logger = new \CreatorCore\Debug\DebugLogger();
    }
    
    public function process_message(string $message, string $conversation_id): array {
        // Carica contesto WP
        $context = $this->context_loader->get_context();
        
        // Carica history conversazione
        $history = $this->get_conversation_history($conversation_id);
        
        // Invia a Firebase
        $ai_response = $this->proxy_client->send_message([
            'prompt' => $message,
            'context' => $context,
            'conversation_history' => $history,
        ]);
        
        // Log per debug
        $this->debug_logger->log($message, $ai_response);
        
        // Processa risposta
        $result = $this->response_handler->handle($ai_response, $context);
        
        // Se continue_automatically, continua il loop
        if ($result['continue_automatically'] ?? false) {
            return $this->continue_loop($result, $conversation_id, $context);
        }
        
        // Salva in history
        $this->save_to_history($conversation_id, $message, $ai_response);
        
        return $result;
    }
    
    private function continue_loop(array $result, string $conversation_id, array $context): array {
        // Prepara messaggio di continuazione con risultato esecuzione
        $continuation = [
            'type' => 'execution_result',
            'result' => $result['execution_result'] ?? null,
        ];
        
        // Richiama AI per prossimo step
        $ai_response = $this->proxy_client->send_message([
            'prompt' => json_encode($continuation),
            'context' => $context,
            'conversation_history' => $this->get_conversation_history($conversation_id),
        ]);
        
        $this->debug_logger->log('CONTINUATION', $ai_response);
        
        $new_result = $this->response_handler->handle($ai_response, $context);
        
        if ($new_result['continue_automatically'] ?? false) {
            return $this->continue_loop($new_result, $conversation_id, $context);
        }
        
        return $new_result;
    }
    
    // ... altri metodi helper
}
```

### 5. ResponseHandler.php

**Responsabilità**: Parsing risposta AI e esecuzione azioni

```php
<?php
namespace CreatorCore\Response;

class ResponseHandler {
    
    private $code_executor;
    private $proxy_client;
    
    public function __construct() {
        $this->code_executor = new \CreatorCore\Executor\CodeExecutor();
        $this->proxy_client = new \CreatorCore\Proxy\ProxyClient();
    }
    
    public function handle(array $ai_response, array $context): array {
        $type = $ai_response['type'] ?? 'unknown';
        
        switch ($type) {
            case 'question':
            case 'plan':
            case 'complete':
                // Mostra messaggio all'utente
                return [
                    'type' => $type,
                    'status' => $ai_response['status'] ?? '',
                    'message' => $ai_response['message'] ?? '',
                    'data' => $ai_response['data'] ?? [],
                    'requires_confirmation' => $ai_response['requires_confirmation'] ?? false,
                    'continue_automatically' => false,
                ];
                
            case 'execute':
                // Esegui codice PHP
                $code = $ai_response['data']['code'] ?? '';
                $execution_result = $this->code_executor->execute($code, $context);
                
                return [
                    'type' => 'execute',
                    'status' => $ai_response['status'] ?? '',
                    'message' => $ai_response['message'] ?? '',
                    'data' => $ai_response['data'] ?? [],
                    'execution_result' => $execution_result,
                    'continue_automatically' => $ai_response['continue_automatically'] ?? true,
                ];
                
            case 'verify':
                // Esegui codice di verifica
                $code = $ai_response['data']['code'] ?? '';
                $verification_result = $this->code_executor->execute($code, $context);
                
                return [
                    'type' => 'verify',
                    'status' => $ai_response['status'] ?? '',
                    'message' => $ai_response['message'] ?? '',
                    'verification_result' => $verification_result,
                    'continue_automatically' => $ai_response['continue_automatically'] ?? true,
                ];
                
            case 'request_docs':
                // Recupera documentazione e continua
                $docs = $this->fetch_documentation($ai_response['data']['plugins_needed'] ?? []);
                
                return [
                    'type' => 'request_docs',
                    'status' => $ai_response['status'] ?? 'Fetching documentation...',
                    'message' => $ai_response['message'] ?? '',
                    'documentation' => $docs,
                    'continue_automatically' => true,
                ];
                
            case 'error':
                return [
                    'type' => 'error',
                    'status' => $ai_response['status'] ?? 'Error',
                    'message' => $ai_response['message'] ?? 'An error occurred',
                    'data' => $ai_response['data'] ?? [],
                    'continue_automatically' => $ai_response['data']['recoverable'] ?? false,
                ];
                
            default:
                return [
                    'type' => 'error',
                    'status' => 'Unknown response',
                    'message' => 'Received unknown response type from AI',
                    'continue_automatically' => false,
                ];
        }
    }
    
    private function fetch_documentation(array $plugin_slugs): array {
        $docs = [];
        foreach ($plugin_slugs as $slug) {
            $doc = $this->proxy_client->get_plugin_docs($slug);
            if ($doc) {
                $docs[$slug] = $doc;
            }
        }
        return $docs;
    }
}
```

### 6. CodeExecutor.php

**Responsabilità**: Esecuzione codice PHP

```php
<?php
namespace CreatorCore\Executor;

class CodeExecutor {
    
    public function execute(string $code, array $context = []): array {
        if (empty(trim($code))) {
            return ['success' => false, 'error' => 'Empty code'];
        }
        
        // Variabili disponibili nel contesto
        $context = array_merge($context, [
            'last_result' => $context['last_result'] ?? null,
        ]);
        
        // Cattura output
        ob_start();
        
        try {
            // Esegui il codice
            $result = eval($code);
            
            $output = ob_get_clean();
            
            // Se il codice non ha ritornato nulla, considera successo
            if ($result === null) {
                $result = ['success' => true, 'output' => $output];
            }
            
            return [
                'success' => true,
                'result' => $result,
                'output' => $output,
            ];
            
        } catch (\Throwable $e) {
            ob_end_clean();
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
    }
}
```

### 7. ContextLoader.php

**Responsabilità**: Raccolta informazioni WordPress

```php
<?php
namespace CreatorCore\Context;

class ContextLoader {
    
    public function get_context(): array {
        return [
            'wordpress' => $this->get_wordpress_info(),
            'php' => $this->get_php_info(),
            'mysql' => $this->get_mysql_info(),
            'theme' => $this->get_theme_info(),
            'plugins' => $this->get_plugins_list(),
        ];
    }
    
    private function get_wordpress_info(): array {
        global $wp_version;
        return [
            'version' => $wp_version,
            'locale' => get_locale(),
            'multisite' => is_multisite(),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
        ];
    }
    
    private function get_php_info(): array {
        return [
            'version' => phpversion(),
        ];
    }
    
    private function get_mysql_info(): array {
        global $wpdb;
        return [
            'version' => $wpdb->db_version(),
        ];
    }
    
    private function get_theme_info(): array {
        $theme = wp_get_theme();
        $parent = $theme->parent();
        
        return [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'parent' => $parent ? $parent->get('Name') : null,
        ];
    }
    
    private function get_plugins_list(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);
        
        $plugins = [];
        foreach ($all_plugins as $path => $plugin) {
            $slug = dirname($path);
            if ($slug === '.') {
                $slug = basename($path, '.php');
            }
            
            $plugins[] = [
                'name' => $plugin['Name'],
                'slug' => $slug,
                'version' => $plugin['Version'],
                'active' => in_array($path, $active_plugins),
            ];
        }
        
        return $plugins;
    }
}
```

### 8. ProxyClient.php

**Responsabilità**: Comunicazione con Firebase

```php
<?php
namespace CreatorCore\Proxy;

class ProxyClient {
    
    private $base_url;
    private $token;
    
    public function __construct() {
        $this->base_url = get_option('creator_proxy_url', 'https://us-central1-creator-ai.cloudfunctions.net');
        $this->token = get_option('creator_site_token', '');
    }
    
    public function send_message(array $data): array {
        return $this->request('POST', '/route-request', $data);
    }
    
    public function get_plugin_docs(string $slug, string $version = null): ?string {
        $endpoint = '/plugin-docs/' . $slug;
        if ($version) {
            $endpoint .= '?version=' . urlencode($version);
        }
        
        $response = $this->request('GET', $endpoint);
        return $response['documentation'] ?? null;
    }
    
    public function validate_license(string $license_key): array {
        return $this->request('POST', '/validate-license', [
            'license_key' => $license_key,
            'site_url' => get_site_url(),
        ]);
    }
    
    private function request(string $method, string $endpoint, array $data = []): array {
        $url = $this->base_url . $endpoint;
        
        $args = [
            'method' => $method,
            'timeout' => 120,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];
        
        // Aggiungi Authorization se abbiamo un token
        if (!empty($this->token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }
        
        // Aggiungi body per POST
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        if ($status_code >= 400) {
            return [
                'success' => false,
                'error' => $decoded['error'] ?? 'HTTP Error ' . $status_code,
                'status_code' => $status_code,
            ];
        }
        
        return $decoded ?: [];
    }
}
```

### 9. DebugLogger.php

**Responsabilità**: Logging conversazione per debug

```php
<?php
namespace CreatorCore\Debug;

class DebugLogger {
    
    private $log_file;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = $upload_dir['basedir'] . '/creator-debug.log';
    }
    
    public function log($input, $output): void {
        $entry = [
            'timestamp' => current_time('mysql'),
            'input' => $input,
            'output' => $output,
        ];
        
        $line = json_encode($entry, JSON_PRETTY_PRINT) . "\n\n---\n\n";
        
        file_put_contents($this->log_file, $line, FILE_APPEND | LOCK_EX);
    }
    
    public function get_recent_logs(int $limit = 50): array {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        $content = file_get_contents($this->log_file);
        $entries = explode("\n\n---\n\n", $content);
        $entries = array_filter($entries);
        $entries = array_slice($entries, -$limit);
        
        return array_map(function($entry) {
            return json_decode($entry, true);
        }, $entries);
    }
    
    public function clear(): void {
        if (file_exists($this->log_file)) {
            unlink($this->log_file);
        }
    }
}
```

---

## Specifiche Componenti Firebase

### 1. routeRequest.ts (Semplificato)

```typescript
import { onRequest } from "firebase-functions/v2/https";
import { authenticateRequest } from "../middleware/auth";
import { checkRateLimit } from "../middleware/rateLimit";
import { modelService } from "../services/modelService";
import { logger } from "../lib/logger";

const SYSTEM_PROMPT = `...`; // System prompt completo

export const routeRequest = onRequest(
  { 
    region: "europe-west1",
    memory: "1GiB",
    timeoutSeconds: 300,
    cors: true 
  },
  async (req, res) => {
    // Solo POST
    if (req.method !== "POST") {
      res.status(405).json({ error: "Method not allowed" });
      return;
    }

    try {
      // Autenticazione
      const authResult = await authenticateRequest(req);
      if (!authResult.authenticated) {
        res.status(401).json({ error: authResult.error });
        return;
      }

      // Rate limit
      const rateLimitOk = await checkRateLimit(authResult.claims.license_id);
      if (!rateLimitOk) {
        res.status(429).json({ error: "Rate limit exceeded" });
        return;
      }

      const { prompt, context, conversation_history, documentation } = req.body;

      // Costruisci system prompt
      let fullSystemPrompt = SYSTEM_PROMPT;
      
      // Aggiungi contesto WordPress
      if (context) {
        fullSystemPrompt += `\n\n## WORDPRESS ENVIRONMENT\n${JSON.stringify(context, null, 2)}`;
      }

      // Aggiungi documentazione se presente
      if (documentation && Object.keys(documentation).length > 0) {
        fullSystemPrompt += `\n\n## PLUGIN DOCUMENTATION\n`;
        for (const [slug, doc] of Object.entries(documentation)) {
          fullSystemPrompt += `\n### ${slug}\n${doc}\n`;
        }
      }

      // Costruisci messaggi
      const messages = [];
      
      // Aggiungi history
      if (conversation_history && Array.isArray(conversation_history)) {
        messages.push(...conversation_history);
      }
      
      // Aggiungi messaggio corrente
      messages.push({ role: "user", content: prompt });

      // Chiama AI
      const response = await modelService.generate({
        systemPrompt: fullSystemPrompt,
        messages,
        model: "gemini", // o "claude" basato su config
      });

      // Parsa risposta JSON
      let parsedResponse;
      try {
        // L'AI dovrebbe rispondere con JSON puro
        parsedResponse = JSON.parse(response.content);
      } catch {
        // Se non è JSON valido, wrappa come errore
        parsedResponse = {
          step: "implementation",
          type: "error",
          status: "Parse error",
          message: "AI response was not valid JSON",
          data: { raw_response: response.content },
        };
      }

      res.json({
        success: true,
        response: parsedResponse,
        tokens_used: response.tokensUsed,
        model: response.model,
      });

    } catch (error) {
      logger.error("routeRequest error", { error });
      res.status(500).json({ 
        error: "Internal server error",
        message: error.message 
      });
    }
  }
);
```

### 2. modelService.ts (Semplificato)

```typescript
import { GeminiProvider } from "../providers/gemini";
import { ClaudeProvider } from "../providers/claude";
import { logger } from "../lib/logger";

interface GenerateOptions {
  systemPrompt: string;
  messages: Array<{ role: string; content: string }>;
  model: "gemini" | "claude";
}

interface GenerateResult {
  content: string;
  tokensUsed: number;
  model: string;
}

class ModelService {
  private gemini: GeminiProvider;
  private claude: ClaudeProvider;

  constructor() {
    this.gemini = new GeminiProvider();
    this.claude = new ClaudeProvider();
  }

  async generate(options: GenerateOptions): Promise<GenerateResult> {
    const { systemPrompt, messages, model } = options;

    const primaryProvider = model === "claude" ? this.claude : this.gemini;
    const fallbackProvider = model === "claude" ? this.gemini : this.claude;

    try {
      // Prova provider primario
      const result = await primaryProvider.generate(systemPrompt, messages);
      return {
        content: result.content,
        tokensUsed: result.tokensUsed,
        model: result.model,
      };
    } catch (primaryError) {
      logger.warn(`Primary provider ${model} failed, trying fallback`, { 
        error: primaryError.message 
      });

      try {
        // Prova fallback
        const result = await fallbackProvider.generate(systemPrompt, messages);
        return {
          content: result.content,
          tokensUsed: result.tokensUsed,
          model: result.model,
        };
      } catch (fallbackError) {
        logger.error("Both providers failed", {
          primary: primaryError.message,
          fallback: fallbackError.message,
        });
        throw new Error("All AI providers failed");
      }
    }
  }
}

export const modelService = new ModelService();
```

---

## Piano di Sviluppo

### Fase 1: Pulizia (2-3 giorni)

**Obiettivo**: Rimuovere tutto il codice non necessario

#### Firebase
- [ ] Eliminare `api/analytics/`
- [ ] Eliminare `api/tasks/`
- [ ] Eliminare `triggers/`
- [ ] Eliminare `services/costCalculator.ts`
- [ ] Eliminare `services/jobProcessor.ts`
- [ ] Eliminare `services/aiRouter.ts`
- [ ] Eliminare `providers/openai.ts`
- [ ] Semplificare `routeRequest.ts`
- [ ] Semplificare `modelService.ts`
- [ ] Verificare che `validateLicense` funzioni ancora
- [ ] Verificare che i provider Gemini/Claude funzionino

#### WordPress Plugin
- [ ] Eliminare tutti i file `Backup/`
- [ ] Eliminare tutti i file `Integrations/` (Elementor, ACF, etc.)
- [ ] Eliminare `ActionDispatcher.php` vecchio
- [ ] Eliminare tutti gli ActionHandler specifici
- [ ] Eliminare `SetupWizard.php` complesso
- [ ] Eliminare sistema permessi complesso
- [ ] Mantenere solo struttura base

**Deliverable**: Codice pulito che si compila/attiva senza errori

---

### Fase 2: Core Loop Base (3-4 giorni)

**Obiettivo**: Far funzionare il loop base chat → AI → risposta

#### WordPress Plugin
- [ ] Implementare `Loader.php` semplificato
- [ ] Implementare `ChatInterface.php` (pagina admin)
- [ ] Implementare `ChatController.php` (REST endpoint)
- [ ] Implementare `ProxyClient.php` (comunicazione Firebase)
- [ ] Implementare `ContextLoader.php` (raccolta info WP)
- [ ] Creare UI chat base (HTML/CSS/JS)

#### Firebase
- [ ] Aggiornare `routeRequest.ts` con nuovo formato
- [ ] Aggiornare `modelService.ts` semplificato
- [ ] Creare system prompt base
- [ ] Testare chiamate AI

**Test di Validazione**:
```
1. Aprire pagina chat in WP admin
2. Scrivere "Ciao, dimmi che versione di WordPress ho"
3. AI deve rispondere con la versione corretta
```

**Deliverable**: Chat funzionante con risposte AI

---

### Fase 3: Esecuzione Codice (2-3 giorni)

**Obiettivo**: Eseguire codice PHP generato dall'AI

#### WordPress Plugin
- [ ] Implementare `CodeExecutor.php`
- [ ] Implementare `ResponseHandler.php`
- [ ] Gestire tipo `execute` nella risposta
- [ ] Gestire risultato esecuzione
- [ ] Passare risultato all'AI per continuazione

**Test di Validazione**:
```
1. Scrivere "Crea una pagina chiamata Test"
2. AI deve generare codice PHP
3. Codice deve essere eseguito
4. Pagina "Test" deve apparire in WordPress
```

**Deliverable**: Esecuzione codice PHP funzionante

---

### Fase 4: Loop Multi-Step (2-3 giorni)

**Obiettivo**: Gestire task complessi con più azioni

#### WordPress Plugin
- [ ] Implementare `ConversationManager.php`
- [ ] Gestire `continue_automatically`
- [ ] Gestire conversation history
- [ ] Gestire verifica risultati
- [ ] Gestire errori e retry

**Test di Validazione**:
```
1. Scrivere "Crea 3 pagine: Home, About, Contact"
2. AI deve creare un piano
3. Eseguire le 3 azioni in sequenza
4. Verificare che tutte le pagine esistano
5. Mostrare messaggio di completamento
```

**Deliverable**: Task multi-step funzionanti

---

### Fase 5: Documentazione Plugin (2-3 giorni)

**Obiettivo**: Sistema documentazione on-demand

#### Firebase
- [ ] Verificare `getPluginDocs.ts`
- [ ] Verificare `searchPluginDocs.ts`
- [ ] Verificare repository Firestore

#### WordPress Plugin
- [ ] Gestire tipo `request_docs` nella risposta
- [ ] Recuperare documentazione da Firebase
- [ ] Passare documentazione all'AI
- [ ] Continuare conversazione con doc

**Test di Validazione**:
```
1. Scrivere "Ottimizza la configurazione della cache" (con plugin cache installato)
2. AI deve richiedere documentazione del plugin
3. Documentazione deve essere recuperata
4. AI deve rispondere con suggerimenti specifici
```

**Deliverable**: Sistema documentazione funzionante

---

### Fase 6: Task Complessi (3-4 giorni)

**Obiettivo**: Validare con task reali complessi

**Test di Validazione 1 - Homepage Elementor**:
```
"Crea la homepage del sito usando Elementor con:
- Hero Section con titolo e CTA
- Features Section a 3 colonne
- Testimonials
- Pricing
- FAQ accordion
- Footer con contatti"
```

**Test di Validazione 2 - Configurazione Plugin**:
```
"Configura RankMath SEO con:
- Sitemap abilitata
- Schema markup per organizzazione
- Redirect 301 da vecchie URL"
```

**Test di Validazione 3 - E-commerce**:
```
"Crea 5 prodotti WooCommerce per un negozio di magliette,
con prezzi, descrizioni, e immagini placeholder"
```

**Deliverable**: Task complessi funzionanti

---

### Fase 7: UI e Debug (2 giorni)

**Obiettivo**: Migliorare UX e debugging

#### WordPress Plugin
- [ ] Implementare `DebugLogger.php`
- [ ] Aggiungere status log nella UI
- [ ] Aggiungere debug panel collapsabile
- [ ] Migliorare gestione errori UI
- [ ] Polish CSS

**Deliverable**: UI completa e debug funzionante

---

## Test di Validazione

### Test Essenziali (Must Pass)

| # | Test | Criterio di Successo |
|---|------|---------------------|
| 1 | Chat base | AI risponde a "Ciao" |
| 2 | Contesto WP | AI conosce versione WP/PHP |
| 3 | Creazione pagina | "Crea pagina Test" → pagina creata |
| 4 | Creazione articolo | "Scrivi articolo su X" → articolo creato |
| 5 | Multi-step | "Crea 3 pagine" → 3 pagine create |
| 6 | Errore recovery | AI gestisce errori e riprova |
| 7 | Plugin docs | AI richiede e usa documentazione |

### Test Avanzati (Should Pass)

| # | Test | Criterio di Successo |
|---|------|---------------------|
| 8 | Elementor page | Homepage completa con sezioni |
| 9 | WooCommerce | Prodotti creati correttamente |
| 10 | Config plugin | Settings modificati correttamente |
| 11 | Custom code | Snippet aggiunto a functions.php |
| 12 | Conversazione lunga | 10+ scambi senza perdere contesto |

### Test di Stress (Nice to Pass)

| # | Test | Criterio di Successo |
|---|------|---------------------|
| 13 | Task molto complesso | Homepage 8+ sezioni |
| 14 | Errori multipli | Recovery da 3+ errori consecutivi |
| 15 | Timeout | Task lungo (>2 min) completato |

---

## Note Finali

### Priorità Assolute

1. **Funziona > Perfetto**: L'obiettivo è un MVP funzionante, non codice perfetto
2. **Semplicità > Flessibilità**: Meno codice = meno bug
3. **Test reali > Test unitari**: Validare con casi d'uso reali

### Cosa NON fare

- Non aggiungere features non richieste
- Non ottimizzare prematuramente
- Non preoccuparsi della sicurezza (per ora)
- Non creare integrazioni specifiche per plugin
- Non implementare backup/rollback

### Quando Chiedere Chiarimenti

- Se una specifica non è chiara
- Se un test fallisce e non sai perché
- Se devi fare una scelta architetturale importante
- Se un task richiede più di 1 giorno

---

*Documento creato: Dicembre 2025*
*Versione: 1.0*
*Stato: Pronto per Implementazione*
