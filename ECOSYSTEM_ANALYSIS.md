# Creator Ecosystem - Analisi Completa dell'Architettura

> **Versione**: 1.0
> **Data**: Dicembre 2024
> **Autore**: Analisi Automatizzata Claude

---

## 1. Executive Summary

**Creator** è un ecosistema completo per WordPress che integra intelligenza artificiale (Gemini, Claude, OpenAI) direttamente nell'ambiente di amministrazione WordPress. L'architettura è suddivisa in tre componenti principali:

1. **Plugin WordPress** (`creator-core-plugin`): Interfaccia utente, gestione chat, esecuzione azioni
2. **Proxy Firebase** (`functions`): Backend TypeScript per routing AI, autenticazione, gestione licenze
3. **Infrastruttura Cloud**: Firebase Functions, Firestore, autenticazione JWT

---

## 2. Mappa Visuale dell'Ecosistema

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           CREATOR ECOSYSTEM                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │                    WORDPRESS (Plugin PHP)                            │   │
│   │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌────────────┐  │   │
│   │  │    Chat     │  │   Context   │  │   Action    │  │   Backup   │  │   │
│   │  │  Interface  │──│   Manager   │──│   Executor  │──│   System   │  │   │
│   │  └──────┬──────┘  └─────────────┘  └──────┬──────┘  └────────────┘  │   │
│   │         │                                  │                         │   │
│   │         │         ┌─────────────┐         │                         │   │
│   │         └────────▶│   Proxy     │◀────────┘                         │   │
│   │                   │   Client    │                                   │   │
│   │                   └──────┬──────┘                                   │   │
│   └──────────────────────────┼──────────────────────────────────────────┘   │
│                              │                                               │
│                              │ HTTPS + JWT                                   │
│                              ▼                                               │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │                 FIREBASE PROXY (TypeScript Functions)                │   │
│   │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌────────────┐  │   │
│   │  │     AI      │  │   License   │  │    Job      │  │   Usage    │  │   │
│   │  │   Router    │  │   Manager   │  │  Processor  │  │  Tracking  │  │   │
│   │  └──────┬──────┘  └─────────────┘  └─────────────┘  └────────────┘  │   │
│   │         │                                                            │   │
│   │         ▼                                                            │   │
│   │  ┌─────────────────────────────────────────────────────────────┐    │   │
│   │  │                    AI PROVIDERS                              │    │   │
│   │  │   ┌─────────┐    ┌─────────┐    ┌─────────┐                 │    │   │
│   │  │   │ Gemini  │    │ Claude  │    │ OpenAI  │                 │    │   │
│   │  │   │  Pro    │    │ Sonnet  │    │ GPT-4o  │                 │    │   │
│   │  │   └─────────┘    └─────────┘    └─────────┘                 │    │   │
│   │  └─────────────────────────────────────────────────────────────┘    │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
│                              │                                               │
│                              ▼                                               │
│   ┌─────────────────────────────────────────────────────────────────────┐   │
│   │                        FIRESTORE DATABASE                            │   │
│   │   ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐           │   │
│   │   │ Licenses │  │   Jobs   │  │  Usage   │  │  Costs   │           │   │
│   │   └──────────┘  └──────────┘  └──────────┘  └──────────┘           │   │
│   └─────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Struttura delle Directory

```
creator/
├── packages/
│   └── creator-core-plugin/
│       └── creator-core/           # Plugin WordPress principale
│           ├── creator-core.php    # Entry point del plugin
│           ├── assets/             # CSS, JS, immagini
│           └── includes/           # Classi PHP
│               ├── API/            # REST API endpoints
│               ├── Audit/          # Logging e tracking operazioni
│               ├── Backup/         # Sistema snapshot e rollback
│               ├── Chat/           # Interfaccia chat AI
│               ├── Context/        # Gestione contesto sito
│               ├── Development/    # File system, plugin generator
│               ├── Executor/       # Esecuzione azioni AI
│               ├── Integrations/   # ProxyClient, plugin detection
│               ├── Permission/     # Gestione permessi
│               └── User/           # Profili utente e preferenze
│
├── functions/
│   └── src/                        # Firebase Functions (TypeScript)
│       ├── index.ts                # Entry point, registrazione endpoints
│       ├── lib/                    # Utilities e helpers
│       │   ├── firestore.ts        # Operazioni database
│       │   └── logger.ts           # Sistema logging
│       ├── providers/              # Client AI providers
│       │   ├── claude.ts           # Anthropic Claude
│       │   ├── gemini.ts           # Google Gemini
│       │   └── openai.ts           # OpenAI GPT
│       ├── services/               # Business logic
│       │   ├── aiRouter.ts         # Routing intelligente AI
│       │   ├── jobProcessor.ts     # Elaborazione job asincroni
│       │   └── taskProcessors/     # Processori task specifici
│       └── types/                  # Type definitions
│           ├── AIProvider.ts       # Interfacce AI
│           ├── Job.ts              # Tipi job asincroni
│           └── Route.ts            # Tipi routing
│
└── firebase.json                   # Configurazione Firebase
```

---

## 4. Architettura Logica

### 4.1 Modello a Tre Livelli

```
┌────────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                          │
│  (WordPress Admin Dashboard, Chat UI, Settings Pages)          │
├────────────────────────────────────────────────────────────────┤
│                    BUSINESS LOGIC LAYER                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ ChatInterface│  │ActionExecutor│  │  AIRouter    │         │
│  │   (PHP)      │  │    (PHP)     │  │    (TS)      │         │
│  └──────────────┘  └──────────────┘  └──────────────┘         │
├────────────────────────────────────────────────────────────────┤
│                    DATA ACCESS LAYER                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │  WordPress   │  │   Firebase   │  │ AI Provider  │         │
│  │   Database   │  │   Firestore  │  │    APIs      │         │
│  └──────────────┘  └──────────────┘  └──────────────┘         │
└────────────────────────────────────────────────────────────────┘
```

### 4.2 Flusso di Comunicazione

```
1. USER → WordPress Dashboard
2. WordPress → ChatInterface.php (gestione messaggio)
3. ChatInterface → ProxyClient.php (invio a proxy)
4. ProxyClient → Firebase Functions (HTTPS + JWT)
5. Firebase → AIRouter (selezione provider)
6. AIRouter → Provider (Gemini/Claude/OpenAI)
7. Provider → Response → WordPress
8. WordPress → ActionExecutor (esecuzione azioni)
9. ActionExecutor → WordPress Database (modifiche)
10. ActionExecutor → SnapshotManager (backup delta)
```

---

## 5. Dettaglio Componenti PHP (Plugin WordPress)

### 5.1 `/includes/Loader.php`
**Scopo**: Bootstrap principale del plugin
**Responsabilità**:
- Registrazione autoloader PSR-4
- Inizializzazione componenti
- Hook WordPress (activation, deactivation)
- Caricamento dipendenze

### 5.2 `/includes/API/REST_API.php`
**Scopo**: Registrazione e gestione REST API endpoints
**Endpoints principali**:
- `POST /creator/v1/chat/send` - Invio messaggi chat
- `POST /creator/v1/actions/execute` - Esecuzione azioni AI
- `GET /creator/v1/context/refresh` - Refresh contesto sito
- `POST /creator/v1/license/validate` - Validazione licenza

### 5.3 `/includes/Chat/ChatInterface.php`
**Scopo**: Gestione conversazioni AI
**Funzionalità**:
- Creazione/gestione sessioni chat
- Formattazione messaggi per AI
- Parsing risposte e azioni
- History management

### 5.4 `/includes/Context/CreatorContext.php`
**Scopo**: Generazione contesto sito per AI
**Produce**:
```php
[
    'meta'              => [...],           // Versione, timestamp
    'user_profile'      => [...],           // Livello competenza
    'system_info'       => [...],           // WP version, PHP, theme
    'plugins'           => [...],           // Plugin attivi
    'custom_post_types' => [...],           // CPT registrati
    'taxonomies'        => [...],           // Tassonomie custom
    'acf_fields'        => [...],           // Gruppi ACF
    'integrations'      => [...],           // Elementor, WooCommerce
    'sitemap'           => [...],           // Struttura pagine
    'system_prompts'    => [...],           // Istruzioni AI per livello
]
```

### 5.5 `/includes/Executor/ActionExecutor.php`
**Scopo**: Esecuzione azioni richieste dall'AI
**Azioni supportate**:

| Categoria | Azioni |
|-----------|--------|
| **Post/Page** | `create_post`, `create_page`, `update_post`, `delete_post` |
| **Meta** | `update_meta` |
| **File System** | `read_file`, `write_file`, `delete_file`, `list_directory`, `search_files` |
| **Plugin** | `create_plugin`, `activate_plugin`, `deactivate_plugin`, `add_plugin_file` |
| **Code Analysis** | `analyze_code`, `analyze_plugin`, `debug_error`, `get_debug_log` |
| **Database** | `db_query`, `db_insert`, `db_update`, `db_delete`, `db_create_table` |
| **Elementor** | `add_elementor_widget` |

### 5.6 `/includes/Backup/SnapshotManager.php`
**Scopo**: Sistema di backup e rollback
**Funzionalità**:
- Creazione snapshot delta per ogni operazione
- Storage file JSON con istruzioni rollback
- Cleanup automatico snapshot vecchi
- Gestione limiti dimensione backup

### 5.7 `/includes/User/UserProfile.php`
**Scopo**: Gestione profili utente e personalizzazione AI
**Livelli competenza**:

| Livello | Descrizione | Comportamento AI |
|---------|-------------|------------------|
| `base` | Non programma | Solo plugin e interfacce visuali, linguaggio semplice |
| `intermediate` | Conosce basi HTML/CSS/PHP | Mix plugin + codice via WP Code, linguaggio tecnico |
| `advanced` | Sviluppatore | Soluzioni tecnicamente migliori, codice diretto |

**Modelli AI**:
- `gemini` - Gemini 3 Pro (reasoning complesso)
- `claude` - Claude Sonnet 4 (coding preciso)

### 5.8 `/includes/Integrations/ProxyClient.php`
**Scopo**: Comunicazione con Firebase Proxy
**Funzionalità**:
- Validazione licenza
- Invio richieste AI con contesto
- Gestione token JWT
- Refresh automatico token scaduti

### 5.9 `/includes/Integrations/PluginDetector.php`
**Scopo**: Rilevamento plugin e integrazioni
**Plugin rilevati**:
- Elementor
- WooCommerce
- ACF Pro
- Rank Math
- WP Code

---

## 6. Dettaglio Componenti TypeScript (Firebase Functions)

### 6.1 `/src/index.ts`
**Scopo**: Entry point Firebase Functions
**Exports**:
```typescript
exports.routeRequest     // Routing richieste AI
exports.validateLicense  // Validazione licenze
exports.processJob       // Elaborazione job asincroni
exports.getUsageStats    // Statistiche utilizzo
```

### 6.2 `/src/services/aiRouter.ts`
**Scopo**: Routing intelligente tra provider AI
**Logica**:
1. Riceve richiesta con `task_type`
2. Seleziona provider primario da routing matrix
3. Tenta generazione
4. Se fallisce, passa a fallback
5. Traccia costi e token

**Routing Matrix**:
```typescript
TEXT_GEN:      Gemini Flash → OpenAI Mini → Claude
CODE_GEN:      Claude → OpenAI GPT-4o → Gemini Pro
DESIGN_GEN:    Gemini Pro → OpenAI GPT-4o → Claude
ECOMMERCE_GEN: Gemini Pro → OpenAI GPT-4o → Claude
```

### 6.3 `/src/providers/claude.ts`
**Scopo**: Client Anthropic Claude
**Funzionalità**:
- Chiamate API messages
- Supporto multimodale (immagini)
- Token counting con tiktoken
- Retry con exponential backoff
- Gestione errori rate limit

### 6.4 `/src/providers/gemini.ts`
**Scopo**: Client Google Gemini
**Modelli**:
- `gemini-2.5-flash-preview-05-20` (veloce/economico)
- `gemini-2.5-pro-preview-05-06` (reasoning avanzato)

### 6.5 `/src/providers/openai.ts`
**Scopo**: Client OpenAI
**Modelli**:
- `gpt-4o` (alta qualità)
- `gpt-4o-mini` (economico)

### 6.6 `/src/types/AIProvider.ts`
**Scopo**: Definizioni tipo comuni AI
**Interfacce chiave**:
```typescript
interface IAIProvider {
  generate(prompt: string, options?: GenerateOptions): Promise<AIResponse>;
  countTokens(text: string): Promise<number>;
  getModel(): string;
  getProviderName(): ProviderName;
}

interface AIResponse {
  success: boolean;
  provider: ProviderName;
  model: string;
  content: string;
  tokens_input: number;
  tokens_output: number;
  cost_usd: number;
  latency_ms: number;
}
```

### 6.7 `/src/types/Route.ts`
**Scopo**: Tipi per routing richieste
**Task Types**:
- `TEXT_GEN` - Generazione testo/articoli
- `CODE_GEN` - Generazione codice
- `DESIGN_GEN` - Layout e design
- `ECOMMERCE_GEN` - Prodotti e-commerce

### 6.8 `/src/lib/firestore.ts`
**Scopo**: Operazioni database Firestore
**Collezioni**:
- `licenses` - Licenze utenti
- `job_queue` - Job asincroni
- `usage_tracking` - Statistiche utilizzo
- `cost_tracking` - Tracking costi per provider

### 6.9 `/src/services/jobProcessor.ts`
**Scopo**: Elaborazione job bulk asincroni
**Task supportati**:
- `bulk_articles` - Generazione multipla articoli
- `bulk_products` - Descrizioni prodotti WooCommerce
- `design_batch` - Batch sezioni design

---

## 7. Flusso Dati Completo

### 7.1 Chat con AI

```
┌──────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│ User │────▶│ ChatInterface│────▶│ ProxyClient │────▶│ Firebase    │
│      │     │    .php      │     │    .php     │     │ Functions   │
└──────┘     └─────────────┘     └─────────────┘     └─────────────┘
                   │                                        │
                   │ 1. Prepara contesto                   │ 2. route-request
                   │ 2. Formatta prompt                    │ 3. Seleziona provider
                   │                                       │ 4. Genera risposta
                   ▼                                       ▼
           ┌─────────────┐                         ┌─────────────┐
           │   Context   │                         │   AIRouter  │
           │   Manager   │                         │     .ts     │
           └─────────────┘                         └─────────────┘
                                                          │
                   ┌──────────────────────────────────────┘
                   ▼
           ┌─────────────────────────────────────────────────┐
           │              AI PROVIDERS                        │
           │  ┌─────────┐   ┌─────────┐   ┌─────────┐        │
           │  │ Gemini  │   │ Claude  │   │ OpenAI  │        │
           │  └─────────┘   └─────────┘   └─────────┘        │
           └─────────────────────────────────────────────────┘
                   │
                   ▼ Response con azioni
           ┌─────────────┐
           │   Action    │
           │   Executor  │
           │    .php     │
           └─────────────┘
                   │
     ┌─────────────┼─────────────┐
     ▼             ▼             ▼
┌─────────┐  ┌─────────┐  ┌─────────┐
│WordPress│  │ Snapshot │  │  Audit  │
│   DB    │  │ Manager  │  │ Logger  │
└─────────┘  └─────────┘  └─────────┘
```

### 7.2 Esecuzione Azione

```
1. AI restituisce JSON con azione
   {
     "type": "create_page",
     "params": {
       "title": "Nuova Pagina",
       "content": "...",
       "use_elementor": true
     }
   }

2. ActionExecutor riceve azione
   └── Verifica permessi (CapabilityChecker)
   └── Avvia tracking operazione (OperationTracker)
   └── Cattura stato "before" (DeltaBackup)

3. Esecuzione operazione
   └── Mapping action_type → metodo interno
   └── Chiamata WordPress API (wp_insert_post, ecc.)
   └── Gestione errori

4. Post-esecuzione
   └── Cattura stato "after"
   └── Crea snapshot (SnapshotManager)
   └── Completa tracking operazione
   └── Restituisce risultato
```

---

## 8. Sistema di Licenze

### 8.1 Flusso Validazione

```
WordPress                    Firebase
    │                            │
    │ POST /validate-license     │
    │ { license_key, site_url }  │
    │──────────────────────────▶│
    │                            │
    │                            ├── Verifica Firestore
    │                            │   - license esiste?
    │                            │   - non scaduta?
    │                            │   - quota token ok?
    │                            │
    │                            ├── Genera JWT
    │                            │   - license_id
    │                            │   - site_url
    │                            │   - exp: 7 giorni
    │                            │
    │◀──────────────────────────│
    │ { success, site_token }   │
    │                            │
    ├── Salva site_token        │
    └── Imposta transient       │
```

### 8.2 Struttura Licenza Firestore

```javascript
{
  license_id: "CREATOR-2025-XXX-XXX",
  email: "user@example.com",
  plan: "pro",                    // free, pro, unlimited
  tokens_total: 1000000,
  tokens_used: 250000,
  expires_at: Timestamp,
  sites: [
    { url: "https://site.com", validated_at: Timestamp }
  ],
  created_at: Timestamp
}
```

---

## 9. Sistema di Backup e Rollback

### 9.1 Architettura Snapshot

```
wp-content/uploads/creator-backups/
├── 2024-12-01/
│   ├── chat_1/
│   │   ├── snapshot_msg_5_1701432000.json
│   │   └── snapshot_msg_8_1701435600.json
│   └── chat_2/
│       └── snapshot_msg_3_1701439200.json
└── 2024-12-02/
    └── chat_1/
        └── snapshot_msg_12_1701518400.json
```

### 9.2 Struttura Snapshot JSON

```json
{
  "snapshot_id": 42,
  "chat_id": 1,
  "message_id": 5,
  "action_id": 17,
  "timestamp": "2024-12-01T10:00:00Z",
  "operations": [
    {
      "type": "create_page",
      "target": "new-page",
      "before": null,
      "after": {
        "post_id": 123,
        "post_title": "Nuova Pagina",
        "post_status": "draft"
      },
      "status": "completed"
    }
  ],
  "rollback_instructions": [
    {
      "action": "delete_post",
      "post_id": 123
    }
  ]
}
```

---

## 10. Sistema di Profili Utente

### 10.1 Adattamento Comportamento AI

| Aspetto | Base | Intermediate | Advanced |
|---------|------|--------------|----------|
| **Soluzioni** | Solo plugin/GUI | Mix plugin + codice | Tecnicamente migliori |
| **Linguaggio** | Semplice, no gergo | Tecnico ma chiaro | Sviluppatore |
| **Codice** | Mai | Via WP Code | Diretto in functions.php |
| **Conferme** | Sempre | Su operazioni importanti | Solo distruttive |
| **Spiegazioni** | Dettagliate | Concise | Trade-off tecnici |

### 10.2 System Prompts per Livello

L'AI riceve istruzioni specifiche basate sul livello:

**Regole Universali** (tutti i livelli):
- Comprensione completa PRIMA dell'azione
- Uso del contesto sito
- Fallback a domande se ambiguo
- Risposta nella lingua dell'utente

**Regole Specifiche**:
- Base: niente codice, guida passo-passo
- Intermediate: proponi alternative, spiega pro/contro
- Advanced: architettura, performance, trade-off

---

## 11. Pricing e Cost Tracking

### 11.1 Costi per Provider

| Provider | Modello | Input ($/1K) | Output ($/1K) |
|----------|---------|--------------|---------------|
| **Gemini** | 2.5 Flash | $0.00015 | $0.0006 |
| **Gemini** | 2.5 Pro | $0.00125 | $0.01 |
| **Claude** | Sonnet 4 | $0.003 | $0.015 |
| **Claude** | Opus 4.5 | $0.015 | $0.075 |
| **OpenAI** | GPT-4o mini | $0.00015 | $0.0006 |
| **OpenAI** | GPT-4o | $0.005 | $0.015 |

### 11.2 Tracking in Firestore

```javascript
// Collection: cost_tracking/{license_id}/daily/{date}
{
  date: "2024-12-01",
  providers: {
    gemini: { input_tokens: 50000, output_tokens: 20000, cost_usd: 0.25 },
    claude: { input_tokens: 10000, output_tokens: 5000, cost_usd: 0.12 }
  },
  total_cost_usd: 0.37
}
```

---

## 12. Integrazioni Plugin

### 12.1 Plugin Rilevati Automaticamente

| Plugin | Funzionalità Abilitate |
|--------|------------------------|
| **Elementor** | Creazione pagine visual, widget, template |
| **WooCommerce** | Prodotti, ordini, checkout |
| **ACF Pro** | Field groups, repeater, flexible content |
| **Rank Math** | SEO meta, sitemap, schema |
| **WP Code** | Snippet management sicuro |

### 12.2 Context Injection

Per ogni plugin rilevato, l'AI riceve:
- Versione installata
- Funzioni PHP disponibili
- Best practices specifiche
- Limitazioni note

---

## 13. Sicurezza

### 13.1 Permessi e Capability

```php
// CapabilityChecker verifica prima di ogni azione
$capability_map = [
    'create_post'    => 'publish_posts',
    'update_post'    => 'edit_posts',
    'delete_post'    => 'delete_posts',
    'write_file'     => 'manage_options',
    'db_query'       => 'manage_options',
    'activate_plugin'=> 'activate_plugins',
];
```

### 13.2 Funzioni Vietate

L'AI non può mai eseguire:
- `eval()`
- `exec()`
- `shell_exec()`
- Query SQL non parametrizzate
- Modifiche a `wp-config.php`

### 13.3 Autenticazione

- JWT con scadenza 7 giorni
- Validazione site_url
- Rate limiting per licenza (100 req/min)
- Refresh automatico token

---

## 14. Tabelle Database WordPress

### 14.1 Schema

```sql
-- Chat sessions
CREATE TABLE {prefix}creator_chats (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    title VARCHAR(255),
    model VARCHAR(50) DEFAULT 'gemini',
    status ENUM('active', 'archived') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Chat messages
CREATE TABLE {prefix}creator_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT NOT NULL,
    role ENUM('user', 'assistant', 'system'),
    content LONGTEXT,
    actions JSON,
    tokens_used INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES {prefix}creator_chats(id)
);

-- Operation tracking
CREATE TABLE {prefix}creator_operations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    message_id BIGINT NOT NULL,
    action_type VARCHAR(100),
    target VARCHAR(255),
    status ENUM('pending', 'running', 'completed', 'failed'),
    steps JSON,
    result JSON,
    started_at DATETIME,
    completed_at DATETIME
);

-- Snapshots for rollback
CREATE TABLE {prefix}creator_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT NOT NULL,
    message_id BIGINT NOT NULL,
    action_id BIGINT,
    snapshot_type ENUM('DELTA', 'FULL'),
    operations JSON,
    storage_file VARCHAR(500),
    storage_size_kb INT DEFAULT 0,
    deleted TINYINT DEFAULT 0,
    deleted_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Audit log
CREATE TABLE {prefix}creator_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT,
    action VARCHAR(100),
    category VARCHAR(50),
    level ENUM('info', 'warning', 'error', 'success'),
    data JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## 15. File Index Completo

### 15.1 Plugin PHP (`packages/creator-core-plugin/creator-core/`)

| File | Descrizione | Output/Prodotto |
|------|-------------|-----------------|
| `creator-core.php` | Entry point plugin | Registra plugin, costanti, hooks |
| `includes/Loader.php` | Bootstrap | Inizializza tutti i componenti |
| `includes/API/REST_API.php` | REST endpoints | Endpoints `/creator/v1/*` |
| `includes/Chat/ChatInterface.php` | Gestione chat | Sessioni, messaggi, parsing |
| `includes/Chat/ContextCollector.php` | Raccolta contesto | Dati sito per AI |
| `includes/Context/CreatorContext.php` | Documento contesto | JSON contesto completo |
| `includes/Context/SystemPrompts.php` | Prompt sistema | Istruzioni AI per livello |
| `includes/Context/PluginDocsRepository.php` | Docs plugin | Documentazione plugin per AI |
| `includes/Executor/ActionExecutor.php` | Esecuzione azioni | Modifica WordPress |
| `includes/Executor/OperationFactory.php` | Factory operazioni | Crea istanze operazioni |
| `includes/Executor/ErrorHandler.php` | Gestione errori | Logging, recovery |
| `includes/Backup/SnapshotManager.php` | Gestione snapshot | File JSON rollback |
| `includes/Backup/DeltaBackup.php` | Backup incrementale | Cattura stati before/after |
| `includes/Audit/AuditLogger.php` | Logging audit | Record in database |
| `includes/Audit/OperationTracker.php` | Tracking operazioni | Stato operazioni in corso |
| `includes/Permission/CapabilityChecker.php` | Check permessi | Validazione capabilities |
| `includes/User/UserProfile.php` | Profilo utente | Livello, modello AI, prompt |
| `includes/Integrations/ProxyClient.php` | Client proxy | Comunicazione Firebase |
| `includes/Integrations/PluginDetector.php` | Rilevamento plugin | Integrazioni disponibili |
| `includes/Integrations/ElementorIntegration.php` | Integrazione Elementor | Widget, template |
| `includes/Development/FileSystemManager.php` | Gestione file | Read/write/delete file |
| `includes/Development/PluginGenerator.php` | Generatore plugin | Crea plugin custom |
| `includes/Development/CodeAnalyzer.php` | Analisi codice | Debug, analisi file |
| `includes/Development/DatabaseManager.php` | Gestione DB | Query, CRUD tabelle |

### 15.2 Firebase Functions (`functions/src/`)

| File | Descrizione | Output/Prodotto |
|------|-------------|-----------------|
| `index.ts` | Entry point | Esporta Cloud Functions |
| `lib/firestore.ts` | Operazioni Firestore | CRUD licenze, job, usage |
| `lib/logger.ts` | Sistema logging | Log strutturati JSON |
| `providers/claude.ts` | Client Claude | Chiamate Anthropic API |
| `providers/gemini.ts` | Client Gemini | Chiamate Google AI API |
| `providers/openai.ts` | Client OpenAI | Chiamate OpenAI API |
| `services/aiRouter.ts` | Router AI | Selezione provider, fallback |
| `services/jobProcessor.ts` | Processore job | Elaborazione asincrona |
| `services/taskProcessors/bulkArticleProcessor.ts` | Articoli bulk | Generazione batch articoli |
| `services/taskProcessors/bulkProductProcessor.ts` | Prodotti bulk | Descrizioni WooCommerce |
| `services/taskProcessors/designBatchProcessor.ts` | Design batch | Sezioni layout |
| `types/AIProvider.ts` | Tipi AI | Interfacce, pricing |
| `types/Job.ts` | Tipi job | Task data, risultati |
| `types/Route.ts` | Tipi routing | Request/response routing |

---

## 16. Metriche e Monitoring

### 16.1 Metriche Tracciate

- **Token usage** per licenza, provider, modello
- **Latency** per richiesta AI
- **Cost** per provider e modello
- **Success/failure rate** per azione
- **Operazioni** per tipo e stato

### 16.2 Logging Strutturato

```typescript
// Logger output
{
  "timestamp": "2024-12-01T10:00:00Z",
  "level": "info",
  "provider": "claude",
  "action": "generate",
  "model": "claude-sonnet-4-20250514",
  "tokens_input": 1500,
  "tokens_output": 800,
  "latency_ms": 2340,
  "cost_usd": 0.0165,
  "success": true
}
```

---

## 17. Conclusioni

### 17.1 Punti di Forza

1. **Architettura modulare**: Separazione netta tra WordPress e cloud
2. **Multi-provider AI**: Fallback automatico tra Gemini, Claude, OpenAI
3. **Sistema rollback**: Ogni operazione è reversibile
4. **Adattamento utente**: Comportamento AI personalizzato per competenza
5. **Sicurezza**: Permessi granulari, funzioni vietate, audit trail

### 17.2 Pattern Architetturali

- **Dependency Injection**: Componenti PHP iniettabili
- **Strategy Pattern**: Provider AI intercambiabili
- **Factory Pattern**: Creazione operazioni
- **Repository Pattern**: Accesso dati Firestore
- **Observer Pattern**: Logging e tracking eventi

### 17.3 Stack Tecnologico

| Layer | Tecnologie |
|-------|------------|
| Frontend | WordPress Admin, React (setup wizard) |
| Backend WordPress | PHP 8.0+, PSR-4 autoload |
| Backend Cloud | TypeScript, Firebase Functions |
| Database | MySQL (WordPress), Firestore (Cloud) |
| AI | Anthropic Claude, Google Gemini, OpenAI |
| Auth | JWT, WordPress nonces |

---

*Documento generato automaticamente dall'analisi del codice sorgente.*
