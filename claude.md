# Creator - Documentazione Completa del Progetto

**Versione:** 3.0.0-MVP
**Ultimo Aggiornamento:** Dicembre 2025
**Ambiente:** WordPress Plugin + Firebase Cloud Functions

---

## Linee Guida per lo Sviluppatore AI (Claude)

> **Questa sezione definisce il comportamento e le regole operative per Claude come sviluppatore del progetto.**

### Ruolo e Responsabilità

Il mio ruolo in questo progetto è quello di **developer e sviluppatore software**. Devo operare come un membro del team di sviluppo, non come un consulente generico.

### Regole Operative

1. **Codice di qualità**: Scrivere sempre codice pulito, corretto, ottimizzato e ben documentato. Verificare sempre tutti i collegamenti delle classi che scrivo e che utilizzo.

2. **Comunicazione proattiva**: Se sono incerto riguardo un'implementazione, o se ci sono diverse strade possibili per implementare una funzione, devo **sempre confrontarmi con Michele e chiedere conferma** prima di procedere.

3. **Codice snello**: Non sviluppare mai funzionalità non richieste. Implementare **solo** ciò che viene esplicitamente richiesto e ciò che è strettamente necessario per il funzionamento.

4. **Suggerimenti con approvazione**: Posso suggerire implementazioni extra qualora le ritenga utili, ma devono **sempre essere approvate** da Michele prima di essere implementate.

5. **Debug cleanup**: Se scrivo codice di debug (error_log, console.log, etc.), questo deve essere **sempre rimosso** al risolvimento del problema.

6. **Documentazione aggiornata**: Devo **sempre aggiornare questo file (claude.md)** ogni volta che effettuo modifiche o implementazioni che differiscono da quanto documentato, così da mantenere sempre un documento aggiornato e completo.

7. **Leggere prima di modificare**: Prima di modificare codice esistente, devo **sempre leggere il file** per capire il contesto completo e le dipendenze. Questo evita errori dovuti a mancanza di contesto (es. non sapere se un'option WordPress esiste già o deve essere creata).

### Checklist Pre-Commit

Prima di ogni commit, verificare:
- [ ] Il codice implementa solo ciò che è stato richiesto
- [ ] Non ci sono funzionalità extra non approvate
- [ ] Il codice di debug è stato rimosso
- [ ] I collegamenti tra classi sono corretti
- [ ] claude.md è aggiornato se necessario

---

## Indice

0. [Linee Guida per lo Sviluppatore AI (Claude)](#linee-guida-per-lo-sviluppatore-ai-claude)
1. [Cos'è Creator](#cosè-creator)
2. [Architettura Generale](#architettura-generale)
3. [Firebase Cloud Functions](#firebase-cloud-functions)
4. [Plugin WordPress](#plugin-wordpress)
5. [Guida Deployment](#guida-deployment)
6. [Stato Attuale del Progetto](#stato-attuale-del-progetto)

---

## Cos'è Creator

### La Missione

**Creator** è un plugin WordPress basato su intelligenza artificiale (Gemini e Claude) progettato con un obiettivo ambizioso: **sostituire un'intera agenzia di creazione siti web WordPress**.

Non si tratta di un semplice assistente che risponde a domande o genera contenuti isolati. Creator è un sistema operativo AI completo, capace di comprendere richieste complesse, pianificare strategie di implementazione e **eseguire direttamente modifiche** sul sito WordPress dell'utente.

### Caratteristica Chiave: Universal PHP Engine

> **Elemento Critico**: Creator utilizza un **Universal PHP Engine** dove l'AI genera direttamente **codice PHP eseguibile**.

Invece di un sistema con azioni hardcoded (es. `create_page`, `update_post`), l'AI genera codice PHP che utilizza le API native di WordPress (es. `wp_insert_post()`, `update_option()`). Questo approccio elimina completamente il mapping action→handler e offre flessibilità illimitata.

Quando un utente chiede "Crea una landing page per il mio prodotto con sezione hero, testimonianze e call-to-action", Creator:

1. **Analizza** la richiesta e il contesto del sito
2. **Genera codice PHP** che usa funzioni WordPress native
3. **Valida la sicurezza** del codice (26+ funzioni pericolose bloccate)
4. **Esegue** il codice in ambiente sandboxed con output buffering
5. **Ritorna** risultato strutturato con output, return value, e eventuali errori

### Capacità Operative

Creator può operare su molteplici aspetti di un sito WordPress:

| Categoria | Operazioni Supportate |
|-----------|----------------------|
| **File System** | Scrittura/modifica di temi, child themes, `functions.php`, CSS personalizzati |
| **Contenuti** | Creazione articoli, pagine, prodotti WooCommerce con contenuti completi |
| **Page Building** | Generazione pagine code-based o Elementor-based con layout e design completi |
| **Configurazione** | Setup plugin, impostazioni tema, configurazione ACF, RankMath SEO |
| **Design** | Sviluppo completo dell'aspetto grafico tramite Elementor o CSS custom |

### Come Funziona: Apprendimento Contestuale

Creator non opera "al buio". Prima di ogni operazione:

1. **Riceve informazioni sull'ecosistema attuale** del sito (tema attivo, plugin installati, struttura esistente)
2. **Consulta la documentazione** dei plugin in un repository dedicato per capire come utilizzare gli strumenti disponibili
3. **Si integra perfettamente** con l'ecosistema specifico dell'utente, senza basarsi su sistemi predefiniti

Questo approccio permette a Creator di:
- Lavorare con qualsiasi combinazione di plugin WordPress
- Adattarsi a configurazioni custom esistenti
- Rispettare convenzioni e stili già presenti nel sito

### Sistema Micro-Step per Operazioni Complesse

Per task complessi, Creator utilizza un approccio a micro-step:

1. **Analisi della complessità** - Determina se il task richiede una roadmap
2. **Generazione roadmap** - Crea una sequenza di step numerati
3. **Conferma utente** - L'utente approva o modifica la roadmap
4. **Esecuzione step-by-step** - Ogni step viene eseguito con checkpoint
5. **Verifica progressiva** - Accumula contesto e verifica risultati
6. **Compressione history** - Ottimizza la conversazione per evitare limiti di token

Questo sistema garantisce affidabilità del 95% anche per operazioni complesse, mantenendo il controllo dell'utente sul processo.

---

## Architettura Generale

### Panoramica del Sistema

Creator è composto da due componenti principali che comunicano via HTTPS:

```
┌─────────────────────────────────────────────────────────────┐
│                      WordPress Site                          │
├──────────────────────────────────────────────────────────────┤
│  Creator Plugin (creator-core)                               │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────┐ │
│  │ChatInterface│→ │ChatController│→ │ConversationManager │ │
│  │   (UI)      │  │  (REST API)  │  │  (Orchestration)   │ │
│  └──────────────┘  └──────────────┘  └────────────────────┘ │
│         │                                      │              │
│         ↓                                      ↓              │
│  ┌──────────────┐                    ┌────────────────────┐ │
│  │ContextLoader │                    │ ResponseHandler    │ │
│  │(WP Context)  │                    │ (Parse & Execute)  │ │
│  └──────────────┘                    └────────────────────┘ │
│         │                                      │              │
│         └──────────────┬──────────────────────┘              │
│                        ↓                                      │
│              ┌──────────────┐                                │
│              │ ProxyClient  │←───── JWT Token                │
│              │ (HTTP)       │                                │
│              └──────────────┘                                │
└────────────────────┬─────────────────────────────────────────┘
                     │ HTTPS
                     ↓
┌─────────────────────────────────────────────────────────────┐
│                  Firebase Cloud Functions                    │
├──────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────┐ │
│  │validateLicense│  │ routeRequest │  │  pluginDocs APIs   │ │
│  │  (Auth)      │  │  (AI Proxy)  │  │  (Documentation)   │ │
│  └──────────────┘  └──────────────┘  └────────────────────┘ │
│         │                │                                    │
│         ↓                ↓                                    │
│  ┌────────────────────────────────────────────────────────┐  │
│  │                    Middleware                          │  │
│  │  auth.ts (JWT) | rateLimit.ts (Rate Limiting)         │  │
│  └────────────────────────────────────────────────────────┘  │
│         │                │                                    │
│         ↓                ↓                                    │
│  ┌────────────────────────────────────────────────────────┐  │
│  │                    Services                            │  │
│  │  ModelService (Routing) | PluginDocsResearch (AI)     │  │
│  └────────────────────────────────────────────────────────┘  │
│         │                │                                    │
│         ↓                ↓                                    │
│  ┌──────────────┐  ┌──────────────┐                         │
│  │ Gemini.ts    │  │ Claude.ts    │  ← AI Providers         │
│  │ (Google)     │  │ (Anthropic)  │                         │
│  └──────────────┘  └──────────────┘                         │
│                                                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │                    Firestore                           │  │
│  │  licenses | audit_logs | rate_limits | plugin_docs    │  │
│  └────────────────────────────────────────────────────────┘  │
└───────────────────────────────────────────────────────────────┘
```

### Flusso Operativo Completo

1. **Utente invia messaggio** via ChatInterface
2. **ChatController** (REST API) riceve la richiesta
3. **ContextLoader** raccoglie informazioni sul sito (tema, plugin, versioni)
4. **ProxyClient** invia richiesta a Firebase con JWT token
5. **Firebase valida** token, rate limit, e quota
6. **ModelService** seleziona provider AI (Gemini o Claude con fallback automatico)
7. **AI Provider** genera risposta (codice PHP, istruzioni, roadmap)
8. **ResponseHandler** analizza e esegue la risposta
9. **ConversationManager** gestisce il flusso multi-turn
10. **Risultato** viene mostrato all'utente con azioni eseguibili

---

## Firebase Cloud Functions

### Informazioni Progetto

- **Firebase Project ID**: `creator-ai-proxy`
- **GCP Project Number**: `757337256338`
- **Regioni**: `us-central1` (routeRequest), `europe-west1` (validateLicense)

### Funzioni Esportate

| Funzione | Metodo | Endpoint | Descrizione |
|----------|--------|----------|-------------|
| `validateLicense` | POST | `/api/auth/validate-license` | Valida licenza e genera JWT |
| `routeRequest` | POST | `/api/ai/route-request` | Routing richieste AI con fallback |
| `getPluginDocsApi` | GET | `/api/plugin-docs/:slug/:version` | Recupera docs dalla cache |
| `savePluginDocsApi` | POST | `/api/plugin-docs` | Salva docs in cache |
| `getPluginDocsStatsApi` | GET | `/api/plugin-docs/stats` | Statistiche repository |
| `getPluginDocsAllVersionsApi` | GET | `/api/plugin-docs/all/:slug` | Tutte le versioni plugin |
| `researchPluginDocsApi` | POST | `/api/plugin-docs/research` | Ricerca docs con AI |
| `syncPluginDocsApi` | POST | `/api/plugin-docs/sync` | Sync docs per WordPress |

### Service Accounts

| Service Account | Scopo |
|----------------|-------|
| `757337256338-compute@developer.gserviceaccount.com` | Default Compute Engine - **usato per functions con secrets** |
| `creator-ai-proxy@creator-ai-proxy.iam.gserviceaccount.com` | Default App Engine |
| `firebase-adminsdk-fbsvc@creator-ai-proxy.iam.gserviceaccount.com` | Firebase Admin SDK |

**Importante**: Tutte le funzioni che usano secrets devono specificare `serviceAccount: "757337256338-compute@developer.gserviceaccount.com"`.

### Secrets (Google Cloud Secret Manager)

| Nome Secret | Descrizione |
|-------------|-------------|
| `JWT_SECRET` | Secret per firma JWT token autenticazione |
| `GEMINI_API_KEY` | API key Google Gemini |
| `CLAUDE_API_KEY` | API key Anthropic Claude |

### Collezioni Firestore

#### 1. `licenses`

Gestisce licenze utente e quote.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `license_key` | string | Formato: `CREATOR-YYYY-XXXXX-XXXXX` |
| `site_url` | string | URL sito WordPress registrato |
| `site_token` | string | JWT token per autenticazione |
| `user_id` | string | ID proprietario |
| `plan` | string | `starter` \| `pro` \| `enterprise` |
| `tokens_limit` | number | Limite token mensile |
| `tokens_used` | number | Token utilizzati nel mese corrente |
| `status` | string | `active` \| `suspended` \| `expired` |
| `reset_date` | Timestamp | Data reset quota mensile |
| `expires_at` | Timestamp | Scadenza licenza |
| `created_at` | Timestamp | Data creazione |
| `updated_at` | Timestamp | Ultimo aggiornamento |

#### 2. `audit_logs`

Log di tutte le richieste per compliance e debugging.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `license_id` | string | ID licenza |
| `request_type` | string | `license_validation` \| `ai_request` \| `task_submission` |
| `provider_used` | string | `gemini` \| `claude` |
| `tokens_input` | number | Token input utilizzati |
| `tokens_output` | number | Token output generati |
| `cost_usd` | number | Costo operazione in USD |
| `status` | string | `success` \| `failed` \| `timeout` |
| `ip_address` | string | IP del client |
| `response_time_ms` | number | Tempo risposta in millisecondi |
| `metadata` | object | Dati aggiuntivi specifici della richiesta |
| `created_at` | Timestamp | Data e ora richiesta |

#### 3. `rate_limit_counters`

Contatori per rate limiting per IP.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `endpoint` | string | Endpoint limitato |
| `ip_address` | string | IP del client |
| `hour` | number | Bucket temporale (ora) |
| `count` | number | Contatore richieste |
| `ttl` | Timestamp | Auto-delete dopo 2 minuti |

#### 4. `cost_tracking`

Tracking costi mensili aggregati per licenza.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `license_id` | string | ID licenza |
| `month` | string | Formato: `YYYY-MM` |
| `gemini_tokens_input` | number | Token input Gemini |
| `gemini_tokens_output` | number | Token output Gemini |
| `gemini_cost_usd` | number | Costo totale Gemini |
| `claude_tokens_input` | number | Token input Claude |
| `claude_tokens_output` | number | Token output Claude |
| `claude_cost_usd` | number | Costo totale Claude |
| `total_cost_usd` | number | Costo totale mensile |

#### 5. `plugin_docs_cache`

Cache centralizzata documentazione plugin WordPress e **WordPress Core API**.

> **PRINCIPIO ARCHITETTURALE**: Il sistema NON usa fallback hardcodati. Tutta la documentazione dei plugin viene ricercata dinamicamente tramite AI research, garantendo informazioni sempre aggiornate e complete.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `plugin_slug` | string | Es: `advanced-custom-fields` o `wordpress-core/media` |
| `plugin_version` | string | Es: `6.2.5` o `6.7` (per WP Core) |
| `docs_url` | string | URL documentazione ufficiale |
| `functions_url` | string | URL documentazione funzioni (opzionale) |
| `main_functions` | string[] | **TUTTE** le funzioni principali (senza limite) |
| `api_reference` | string | URL API reference |
| `version_notes` | string[] | Note versione |
| `description` | string | Descrizione completa dell'API/plugin |
| `code_examples` | string[] | Esempi di codice **completi e funzionanti** |
| `best_practices` | string[] | Best practices e linee guida |
| `data_structures` | string[] | Strutture dati (JSON schemas, meta keys) |
| `component_types` | array | Widget/componenti con settings (per page builders) |
| `cached_at` | Timestamp | Data cache |
| `cache_hits` | number | Contatore utilizzi |
| `source` | string | `ai_research` \| `manual` \| `wordpress_core` |
| `research_meta` | object | Metadati ricerca AI (provider, model, tokens, cost) |
| `last_verified` | Timestamp | Ultima verifica completezza |

#### 6. WordPress Core Documentation (Pre-popolata)

La documentazione WordPress Core è pre-popolata e non richiede ricerca AI. Viene servita direttamente dal file `wordpressCoreDocs.ts`.

**Slug format**: `wordpress-core/{topic}`

| Topic | Descrizione |
|-------|-------------|
| `wordpress-core/media` | Media Library API - `wp_insert_attachment()`, `wp_generate_attachment_metadata()`, `media_handle_sideload()` |
| `wordpress-core/posts` | Posts/CPT API - `wp_insert_post()`, `get_posts()`, `update_post_meta()` |
| `wordpress-core/users` | Users API - `wp_insert_user()`, `get_user_by()`, `current_user_can()` |
| `wordpress-core/options` | Options & Transients - `get_option()`, `set_transient()` |
| `wordpress-core/database` | `$wpdb` API - `prepare()`, `insert()`, `update()`, `get_results()` |
| `wordpress-core/hooks` | Actions & Filters - `add_action()`, `add_filter()`, `apply_filters()` |
| `wordpress-core/rest-api` | REST API - `register_rest_route()`, `WP_REST_Request` |
| `wordpress-core/taxonomies` | Taxonomies - `register_taxonomy()`, `wp_set_object_terms()` |
| `wordpress-core/filesystem` | WP_Filesystem API |
| `wordpress-core/scripts-styles` | `wp_enqueue_script()`, `wp_localize_script()` |
| `wordpress-core/ajax` | AJAX handlers - `wp_ajax_*`, `check_ajax_referer()` |
| `wordpress-core/shortcodes` | Shortcode API - `add_shortcode()`, `shortcode_atts()` |
| `wordpress-core/widgets` | Widget API - `WP_Widget` class |

**Ogni topic include**:
- `docs_url`: URL documentazione ufficiale WordPress
- `main_functions`: Funzioni principali con parametri e descrizione
- `code_examples`: Esempi di codice **corretti** e completi
- `best_practices`: Linee guida e avvertenze importanti

**Esempio richiesta AI**:
```json
{
  "type": "request_docs",
  "data": {
    "plugins_needed": ["wordpress-core/media", "elementor"]
  }
}
```

Questo risolve problemi come immagini non registrate nella Media Library, perché l'AI riceve documentazione su come usare correttamente `wp_insert_attachment()` invece di `file_put_contents()`.

**Flusso**:
```
AI rileva task upload immagine
        ↓
Richiede "wordpress-core/media" + "elementor"
        ↓
Firebase ritorna documentazione con code_examples e best_practices
        ↓
AI genera codice CORRETTO che registra l'immagine nella Media Library
```

### Sistema AI Research per Plugin Documentation

Il sistema utilizza un approccio **100% dinamico** per la documentazione dei plugin. Nessun fallback hardcodato.

#### Principi Architetturali

1. **NO Hardcoded Fallbacks**: Il file `pluginDocsResearch.ts` non contiene `KNOWN_PLUGIN_DOCS` o `getFallbackDocs()`. Ogni plugin viene ricercato tramite AI.

2. **Comprehensive Research Prompt**: L'AI riceve un prompt che richiede:
   - **TUTTE** le funzioni principali (senza limite di 15)
   - Esempi di codice **completi e funzionanti**
   - Best practices e linee guida
   - Strutture dati (JSON schemas, meta keys, formati)
   - Per page builders: widget/componenti con relative settings

3. **Plugin-Specific Prompts**: Plugin popolari ricevono prompt specializzati:
   - **Elementor**: Focus su `_elementor_data`, widget types, settings per widget
   - **WooCommerce**: Product data, checkout fields, order management
   - **ACF**: Field groups, field types, location rules

4. **Cache Completeness Check**: Prima di usare la cache, il sistema verifica che contenga:
   - `code_examples` con almeno 1 elemento
   - `best_practices` con almeno 1 elemento
   - `description` con almeno 50 caratteri

   Se incompleta, viene lanciata una nuova ricerca AI.

#### Flusso Plugin Documentation Research

```
WordPress richiede documentazione plugin
        ↓
Firebase verifica cache (plugin_docs_cache)
        ↓
┌─────────────────────────────────────────┐
│ Cache trovata?                          │
│                                         │
│  ├─ NO → Lancia AI Research             │
│  │                                      │
│  └─ SI → Verifica completezza:          │
│         - code_examples presente?       │
│         - best_practices presente?      │
│         - description completa?         │
│                                         │
│         ├─ COMPLETA → Ritorna cache     │
│         │                               │
│         └─ INCOMPLETA → AI Research     │
└─────────────────────────────────────────┘
        ↓
AI Research con prompt comprensivo
        ↓
Salvataggio in Firestore con tutti i campi rich
        ↓
Documentazione completa ritornata a WordPress
```

#### Campi Rich Documentation

Per plugin come Elementor, la documentazione include:

```typescript
{
  plugin_slug: "elementor",
  plugin_version: "3.18.0",
  docs_url: "https://developers.elementor.com/docs/",
  description: "Comprehensive Elementor page builder API...",
  main_functions: [
    "\\Elementor\\Plugin::instance()",
    "update_post_meta($post_id, '_elementor_data', ...)",
    // TUTTE le funzioni, non solo 5-15
  ],
  code_examples: [
    "// Complete working example for creating a page with Elementor\n$page_data = [...];",
    "// Example for adding a specific widget\n$widget_config = [...];"
  ],
  best_practices: [
    "Always use update_post_meta() instead of direct DB access",
    "Set _elementor_edit_mode to 'builder' for proper rendering"
  ],
  data_structures: [
    "Widget JSON structure: {id, elType, widgetType, settings: {...}}",
    "_elementor_data format: JSON array of sections with elements"
  ],
  component_types: [
    {
      name: "Heading",
      type: "heading",
      settings: {
        title: "string",
        header_size: "h1|h2|h3|h4|h5|h6",
        align: "left|center|right"
      },
      example: '{"widgetType":"heading","settings":{"title":"My Title"}}'
    }
  ]
}
```

### Provider AI

#### Modelli Configurati

| Provider | Model ID | Input $/1k | Output $/1k |
|----------|----------|------------|-------------|
| **Claude** | `claude-opus-4-5-20251101` | $0.015 | $0.075 |
| **Gemini** | `gemini-2.5-pro-preview-05-06` | $0.00125 | $0.005 |

#### Strategia Routing

Creator utilizza un sistema di routing semplificato con fallback automatico:

```typescript
DEFAULT_ROUTING = {
  primary: claude,    // Provider primario per tutte le richieste
  fallback: gemini    // Fallback automatico se Claude fallisce
}
```

Se Claude fallisce per qualsiasi motivo (rate limit, errore API, timeout), il sistema prova automaticamente Gemini come fallback, garantendo alta disponibilità del servizio.

### Struttura Directory Firebase

```
functions/
├── src/
│   ├── index.ts                      # Export funzioni
│   ├── api/
│   │   ├── ai/
│   │   │   └── routeRequest.ts       # Endpoint AI principale
│   │   ├── auth/
│   │   │   └── validateLicense.ts    # Validazione licenze
│   │   └── plugin-docs/
│   │       └── pluginDocs.ts         # 6 endpoint plugin docs
│   ├── services/
│   │   ├── modelService.ts           # Logica routing AI
│   │   ├── licensing.ts              # Validazione licenze
│   │   ├── pluginDocsResearch.ts     # Ricerca AI docs plugin
│   │   └── wordpressCoreDocs.ts      # Documentazione WordPress Core API (pre-popolata)
│   ├── providers/
│   │   ├── gemini.ts                 # Provider Gemini
│   │   └── claude.ts                 # Provider Claude
│   ├── lib/
│   │   ├── firestore.ts              # Operazioni database
│   │   ├── jwt.ts                    # Gestione JWT
│   │   ├── secrets.ts                # Definizione secrets
│   │   └── logger.ts                 # Logging strutturato
│   ├── middleware/
│   │   ├── auth.ts                   # Autenticazione JWT
│   │   └── rateLimit.ts              # Rate limiting
│   ├── config/
│   │   └── models.ts                 # Configurazione modelli AI
│   ├── types/
│   │   ├── License.ts
│   │   ├── Route.ts
│   │   ├── PluginDocs.ts
│   │   └── ...
│   └── utils/
│       └── promptUtils.ts            # Validazione prompt
├── .eslintrc.js
├── package.json
├── package-lock.json
└── tsconfig.json
```

### Context WordPress

Il contesto WordPress viene passato nelle richieste AI per personalizzare le risposte:

```typescript
context: {
  wordpress: {
    version: "6.9",
    language: "it_IT",
    is_multisite: false,
    site_url: "https://example.com"
  },
  environment: {
    php_version: "8.2.29",
    mysql_version: "8.0",
    memory_limit: "256M",
    debug_mode: false
  },
  theme: {
    name: "Hello Elementor",
    version: "3.0.0",
    is_child: false
  },
  plugins: [
    { name: "Elementor", version: "3.18.0" },
    { name: "WooCommerce", version: "8.5.0" }
  ]
}
```

Questo contesto viene:
1. Loggato per debug
2. Aggiunto al system prompt dell'AI
3. Inserito come header nel prompt utente

---

## Plugin WordPress

### Informazioni Plugin

- **Nome**: Creator Core
- **Slug**: `creator-core`
- **Namespace PHP**: `CreatorCore\`
- **Versione**: 3.0.0-MVP
- **Autoloader**: PSR-4 custom

### Struttura Directory

```
creator-core/
├── creator-core.php              # Main plugin file
├── composer.json                 # PHP dependencies
├── phpunit.xml                   # PHPUnit configuration
├── uninstall.php                 # Cleanup on uninstall
├── assets/
│   ├── js/
│   │   ├── chat-interface.js     # Chat UI logic
│   │   ├── action-handler.js     # Action execution
│   │   └── debug-panel.js        # Debug functionality
│   └── css/
│       ├── chat-interface.css    # Chat styles
│       └── admin-common.css      # Common admin styles
├── database/
│   ├── migrations.php            # Migration system
│   └── schema.sql                # Database schema
├── includes/
│   ├── Activator.php             # Activation hooks
│   ├── Deactivator.php           # Deactivation hooks
│   ├── Autoloader.php            # PSR-4 autoloader
│   ├── Loader.php                # Plugin bootstrap
│   ├── Admin/
│   │   └── Settings.php          # Settings page
│   ├── Chat/
│   │   ├── ChatInterface.php     # Chat UI registration
│   │   └── ChatController.php    # REST API endpoint
│   ├── Context/
│   │   └── ContextLoader.php     # WP context collection
│   ├── Conversation/
│   │   └── ConversationManager.php # Multi-turn orchestration
│   ├── Debug/
│   │   ├── DebugController.php   # Debug REST endpoints
│   │   └── DebugLogger.php       # Conversation logging
│   ├── Execution/
│   │   └── WPCLIExecutor.php     # WP-CLI command execution
│   ├── Executor/
│   │   └── CodeExecutor.php      # PHP code execution via eval
│   ├── Proxy/
│   │   └── ProxyClient.php       # Firebase HTTP client
│   └── Response/
│       └── ResponseHandler.php   # AI response processing
└── tests/
    ├── bootstrap.php
    ├── stubs/
    │   └── wordpress-stubs.php
    ├── Unit/                     # Unit tests
    └── Integration/              # Integration tests
```

### Componenti Principali

#### 1. ChatInterface & ChatController

**ChatInterface.php** (`CreatorCore\Chat\ChatInterface`)
- Registra la pagina admin nel menu WordPress
- Enqueues assets (JS e CSS)
- Renderizza l'interfaccia HTML della chat

**chat-interface.js** (Frontend)
- Gestisce l'UI della chat e le interazioni utente
- **SSE Real-time Progress**: Usa EventSource invece di AJAX per ricevere progressi in tempo reale
  ```javascript
  // Connessione SSE per streaming progressi
  this.eventSource = new EventSource(streamUrl, { withCredentials: true });

  // Eventi gestiti: connected, progress, complete, error
  this.eventSource.addEventListener('progress', function(event) {
      const data = JSON.parse(event.data);
      self.appendProgressLine(data.detailed_message || data.display_message);
  });
  ```
- **Progress Display in Chat Area**: I progressi vengono mostrati direttamente nell'area chat (non sotto l'input)
  - `createProgressElement()`: Crea elemento con logo Creator e "sta elaborando..." con dots animati
  - `appendProgressLine()`: Aggiunge ogni step come nuova riga di testo grigio
  - `finalizeProgressElement()`: Al completamento, rende il progresso più sbiadito (opacity 0.7)
- **Creator Logo**: Mostra il logo `creator-black.svg` (cerchio nero + freccia bianca) accanto al nome "Creator"
- **Message Width Matching**: Messaggi Creator hanno stessa larghezza max (70%) dei messaggi utente
- **ensureReadableMessage()**: Estrae messaggi leggibili da JSON, con fallback contestuali per tipo
- **File Attachments**: Supporto per allegati multimediali
  - `handleAttachClick()`: Apre il file picker
  - `handleFileSelect()`: Legge i file come base64 e li aggiunge a `attachedFiles[]`
  - `sendMessageWithFiles()`: Invia messaggi con allegati via POST (SSE non supporta file)
  - **Limiti**: Max 5 file per messaggio, max 10MB per file
  - **Tipi accettati**: `image/*`, PDF, DOC/DOCX, XLS/XLSX, TXT, JSON, PHP, JS, CSS, HTML
  ```javascript
  // Routing automatico: POST per file, SSE per solo testo
  if (this.pendingFiles && this.pendingFiles.length > 0) {
      this.sendMessageWithFiles(chatId, message, this.pendingFiles);
  } else {
      // Usa SSE per messaggi senza file
      this.eventSource = new EventSource(streamUrl);
  }
  ```

**ChatController.php** (`CreatorCore\Chat\ChatController`)
- Gestisce l'endpoint REST `POST /wp-json/creator/v1/chat`
- **SSE Streaming Endpoint**: `GET /wp-json/creator/v1/chat/stream` per progressi in tempo reale
- Valida le richieste in ingresso
- Orchestra il flusso della conversazione
- **File Attachments Validation**: Metodo `validate_files()` per validare allegati
  ```php
  // Limiti
  $max_files     = 5;
  $max_file_size = 10 * 1024 * 1024; // 10MB

  // Tipi MIME accettati
  $allowed_types = [
      'image/png', 'image/jpeg', 'image/gif', 'image/webp',
      'application/pdf',
      'text/plain', 'text/html', 'text/css',
      'application/javascript', 'application/json',
      'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  ];

  // Files passati a execute_loop() e poi a ProxyClient
  $final_response = $this->execute_loop($message, $context, ..., $files);
  ```
- Implementa loop handling per roadmap e checkpoint
- **Loop Limit**: Massimo 100 iterazioni per task complessi (`MAX_LOOP_ITERATIONS = 100`)
- **Error Memory System**: Sistema di memoria errori per retry intelligenti
  ```php
  $error_memory = []; // Accumula errori durante i retry

  // Quando uno step fallisce:
  $error_memory[] = [
      'attempt'    => $retry_count,
      'error'      => $last_result['error'],
      'code_tried' => substr($processed['data']['code'], 0, 500),
  ];

  // Passato all'AI nel messaggio di retry per evitare approcci già falliti
  // Viene svuotato dopo ogni step completato con successo
  ```
- **Retry Logic**: Massimo 3 tentativi per step (`MAX_RETRY_ATTEMPTS = 3`), con error memory per evitare ripetizioni
- **SSE Progress Events**: Invia eventi `connected`, `progress`, `complete`, `error` durante l'elaborazione
  - `progress`: Include `display_message` (breve) e `detailed_message` (spiegazione completa AI)
  - `complete`: Contiene la risposta finale con tutti gli step eseguiti

#### 2. ConversationManager

**ConversationManager.php** (`CreatorCore\Conversation\ConversationManager`)
- Orchestrazione conversazioni multi-turn con AI
- Implementa il ciclo a 4 fasi:
  - **Discovery**: Raccolta informazioni e chiarimenti
  - **Strategy**: Pianificazione approccio
  - **Implementation**: Esecuzione operazioni
  - **Verification**: Verifica risultati
- Gestisce roadmap per operazioni complesse
- Accumula contesto tra step successivi

#### 3. ContextLoader

**ContextLoader.php** (`CreatorCore\Context\ContextLoader`)
- Raccoglie informazioni sull'ambiente WordPress:
  - Versione WordPress, PHP, MySQL
  - Tema attivo e se è child theme
  - Plugin installati e attivi con versioni
  - Impostazioni sito (lingua, multisite, URLs)
  - Custom Post Types e tassonomie
- Lazy-loading di contesto dettagliato su richiesta AI

#### 4. ProxyClient

**ProxyClient.php** (`CreatorCore\Proxy\ProxyClient`)
- Client HTTP per comunicazione con Firebase
- Gestisce autenticazione JWT
- Metodi principali:
  - `validate_license()`: Validazione licenza e ottenimento token
  - `send_message()`: Invio richieste AI con context e file
  - `get_plugin_docs()`: Ricerca documentazione plugin
  - `refresh_token()`: Rinnovo automatico token scaduto
- **File Attachments**: `send_message()` accetta parametro `$files` e lo include nel body
  ```php
  public function send_message(
      string $message,
      array $context,
      array $conversation_history = [],
      ?array $documentation = null,
      array $files = []  // File allegati
  ) {
      $body = [
          'task_type' => 'CODE_GEN',
          'prompt'    => $message,
          'context'   => $context,
          'files'     => $files,  // Passati a Firebase
      ];
  }
  ```
- Implementa retry logic con exponential backoff
- Logging completo errori network e HTTP

#### 5. Loader & License Verification

**Loader.php** (`CreatorCore\Loader`)
- Bootstrap principale del plugin
- Registra REST API, admin UI, e license hooks
- **Gestione automatica verifica licenza**:
  - Hook `add_option_creator_license_key`: Prima attivazione licenza
  - Hook `update_option_creator_license_key`: Aggiornamento licenza esistente
  - Verifica automatica quando l'utente salva la licenza dalla Dashboard
  - Salva `site_token` e `license_status` in wp_options

**Flusso verifica licenza**:
```
Utente inserisce licenza → Form submit a options.php
                                    ↓
            WordPress chiama add_option o update_option
                                    ↓
            Hook in Loader.php intercetta l'evento
                                    ↓
            verify_license_key() chiama Firebase API
                                    ↓
            Firebase valida e restituisce site_token + plan
                                    ↓
            Salvataggio in wp_options:
            - creator_site_token (JWT per API calls)
            - creator_license_status (plan, expires_at, tokens)
                                    ↓
            Redirect a Dashboard con verificato
```

**Options WordPress per licenza**:
| Option | Descrizione |
|--------|-------------|
| `creator_license_key` | Chiave licenza (CREATOR-XXXX-XXXX-XXXX) |
| `creator_site_token` | JWT token per autenticazione API |
| `creator_license_status` | Array con valid, plan, expires_at, tokens_used, tokens_limit |

#### 6. Dashboard

**Dashboard.php** (`CreatorCore\Admin\Dashboard`)
- Pagina principale admin con design monochromatic
- Mostra stato licenza, usage credits, system health
- Gestisce lista chat history con paginazione
- Form per inserimento/cambio licenza key
- Notice automatico se licenza non verificata (`maybe_show_license_notice()`)

#### 8. ResponseHandler

**ResponseHandler.php** (`CreatorCore\Response\ResponseHandler`)
- Analizza risposte AI e determina il tipo
- **Passa il context completo al CodeExecutor** per ogni esecuzione di codice
- Gestisce diversi response types:
  - `question`: Domande di chiarimento
  - `plan`: Piani di azione
  - `roadmap`: Roadmap per task complessi
  - `execute`: Esecuzione codice PHP (con context)
  - `execute_step`: Singolo step di roadmap (con context e accumulated_context)
  - `checkpoint`: Verifica intermedia
  - `compress_history`: Compressione conversazione
  - `verify`: Verifica risultati
  - `complete`: Operazione completata
  - `error`: Gestione errori
  - `request_docs`: Richiesta documentazione plugin
  - `wp_cli`: Esecuzione comandi WP-CLI
- **Message Validation System** (`ensure_readable_message()`):
  - Garantisce che i messaggi mostrati all'utente siano sempre leggibili
  - Rileva automaticamente JSON nei campi message e genera messaggi user-friendly
  - Fallback messaggi contestuali basati sul tipo di risposta:
    - `execute_step`: "Executing step X of Y: [step_title]"
    - `checkpoint`: "Progress: X of Y steps completed (Z%)"
    - `execute`/`verify`/`wp_cli`: Messaggi descrittivi appropriati
    - `complete`: "Task completed successfully."
  - Applicato a tutti gli handler: `handle_message_response`, `handle_execute_step_response`, `handle_checkpoint_response`, `handle_execute_response`, `handle_verify_response`, `handle_wp_cli_response`
- **JSON Parsing Robustness** (`parse_ai_content()`):
  - Parsing diretto JSON
  - Estrazione da markdown code blocks
  - Estrazione da testo misto (brace-balanced)
  - Tentativo con `JSON_INVALID_UTF8_SUBSTITUTE` per problemi encoding
  - **Debug logging** quando parsing fallisce (errore + preview content in `debug.log`)

#### 9. CodeExecutor

**CodeExecutor.php** (`CreatorCore\Executor\CodeExecutor`)
- Esegue codice PHP generato dall'AI via `eval()`
- **Context Injection**: Riceve il context completo dal ResponseHandler, disponibile come variabile `$context` nel codice eseguito
- **Context Flattening** (implementato in ChatController): I risultati degli step precedenti vengono automaticamente appiattiti nel context di primo livello:
  ```php
  // Se Step 1 ritorna: return ['page_id' => 202, 'page_url' => 'http://...'];
  // Step 2 può accedere direttamente a:
  $context['page_id']     // 202 (appiattito da last_result['result'])
  $context['page_url']    // http://... (appiattito)

  // Struttura completa disponibile:
  $context['last_result']          // Risultato completo dello step precedente
  $context['accumulated']          // Contesto accumulato dai checkpoint
  $context['site_info']            // Informazioni WordPress

  // I risultati vengono appiattiti SOLO se non sovrascrivono chiavi esistenti
  // Priorità: chiavi originali > last_result['result'] > accumulated
  ```
- Security features:
  - Blacklist di 26+ funzioni pericolose (exec, shell_exec, eval, system, etc.)
  - Validazione sintassi PHP
  - Output buffering per catturare echo/print
  - Error handler custom per catturare errori
  - Timeout configurabile
- **Closure-based execution**: Il codice viene eseguito in una closure che inietta `$context`:
  ```php
  $result = ( function( string $_code, array $context ) {
      return eval( $_code );
  } )( $code, $context );
  ```
- Preparazione codice: wrapping in try-catch, namespace adjustment
- Restituisce output, return value, ed eventuali errori

#### 10. WPCLIExecutor

**WPCLIExecutor.php** (`CreatorCore\Execution\WPCLIExecutor`)
- Esegue comandi WP-CLI in modo sicuro
- Whitelist comandi permessi:
  - `wp post`, `wp page`, `wp media`, `wp menu`, `wp widget`
  - `wp comment`, `wp term`, `wp taxonomy`
  - `wp user` (solo lettura: list, get, meta)
  - `wp option` (get, list, update, add)
  - `wp plugin`, `wp theme` (solo lettura)
  - `wp transient`, `wp cache`, `wp rewrite`, `wp cron`
  - Plugin specifici: `wp wc`, `wp acf`, `wp elementor`, `wp wpcode`
- Blacklist pattern bloccati:
  - `--allow-root`, `eval`, `eval-file`, `shell`
  - `db drop`, `db reset`, `site delete`
  - `plugin install/delete/update`, `theme install/delete/update`
  - `user create/delete/update`
  - Redirect, pipe, chaining, substitution: `>`, `|`, `;`, `&&`, `||`, `` ` ``, `$(`
- Auto-detection path WP-CLI
- Timeout 30 secondi
- Output JSON parsing

#### 11. DebugLogger & DebugController

**DebugLogger.php** (`CreatorCore\Debug\DebugLogger`)
- Logging conversazioni e interazioni AI su file
- Supporta log rotation e limiti di dimensione
- File logs in `wp-content/uploads/creator-logs/`

**DebugController.php** (`CreatorCore\Debug\DebugController`)
- REST endpoints per debug:
  - `GET /wp-json/creator/v1/debug/conversation-history`
  - `GET /wp-json/creator/v1/debug/message/:id`
  - `GET /wp-json/creator/v1/debug/logs`

### Database Tables

Il plugin crea 4 tabelle custom:

| Tabella | Descrizione |
|---------|-------------|
| `{prefix}_creator_conversations` | Conversazioni utente con timestamp e metadata |
| `{prefix}_creator_messages` | Singoli messaggi in conversazioni (user/assistant) |
| `{prefix}_creator_snapshots` | Snapshot pre-operazione per rollback |
| `{prefix}_creator_audit_logs` | Log audit operazioni eseguite |

### System Prompts AI

Il plugin definisce system prompts complessi che istruiscono l'AI su:

1. **Ruolo e capacità**: WordPress assistant con accesso completo al sito
2. **Response format**: JSON strutturato con type, content, actions
3. **Approccio micro-step**: Quando creare roadmap per task complessi
4. **Plugin integration safety**: Come operare sui plugin (API, WP-CLI, manual)
5. **Sicurezza**: Cosa NON fare (mai manipolare internals plugin, sempre verificare docs)
6. **Context management**: Lazy-loading e compressione history

---

## Guida Deployment

### Ambiente di Lavoro

**Directory Locale Progetto**:
```
/Users/michele/creator-ai-proxy/
```

**Utente di Sviluppo**: Michele

**Branch di Lavoro**: Tipicamente feature branches, con merge su `main` per production deploy

### Prerequisiti

#### Node.js Version

**CRITICO**: Il progetto richiede Node.js 20.

Firebase Cloud Build usa Node 20 per compilare le functions. Se generi `package-lock.json` con una versione diversa (es. Node 24), il deployment fallirà.

**Installare e usare Node 20**:

```bash
# Installare nvm (Node Version Manager)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.zshrc  # o source ~/.bashrc

# Installare e usare Node 20
nvm install 20
nvm use 20
```

### Workflow Deployment

#### 1. Sync con GitHub

```bash
cd /Users/michele/creator-ai-proxy

# Sync main branch
git checkout main
git pull origin main

# Fetch branch di lavoro
git fetch origin <branch-name>

# Reset a remote (scarta modifiche locali)
git reset --hard origin/<branch-name>

# OPPURE merge modifiche remote
git pull origin <branch-name> --no-rebase
```

#### 2. Preparare Functions

```bash
cd functions

# IMPORTANTE: Usare Node 20
nvm use 20

# Clean install
rm -rf node_modules package-lock.json
npm install
```

#### 3. Deploy Functions

Deploy tutte le functions:
```bash
firebase deploy --only functions --project creator-ai-proxy
```

Deploy function specifica:
```bash
firebase deploy --only functions:routeRequest --project creator-ai-proxy
firebase deploy --only functions:validateLicense --project creator-ai-proxy
```

#### 4. Gestire Prompt Eliminazione Functions

Quando Firebase rileva functions in cloud che non esistono localmente, chiederà:
```
Would you like to proceed with deletion? (y/N)
```

Rispondere `N` per mantenere le vecchie functions e continuare il deployment.

### Troubleshooting Comuni

#### Errore: Service Account Does Not Exist

**Soluzione**: Aggiungere `serviceAccount` option alla function definition:

```typescript
export const routeRequest = onRequest({
  secrets: [jwtSecret, geminiApiKey, claudeApiKey],
  serviceAccount: "757337256338-compute@developer.gserviceaccount.com",
  // ... altre opzioni
}, async (req, res) => { ... });
```

#### Errore: Package Lock Out of Sync

**Soluzione**:
```bash
nvm use 20
rm -rf node_modules package-lock.json
npm install
```

#### Errore: ESLint Configuration Not Found

**Soluzione**: Verificare che `.eslintrc.js` esista in `functions/`.

#### Functions Non Aggiornate

Se le modifiche non vengono deployate, fare una piccola modifica al file per forzare redeploy:
```bash
# Modificare un commento nella function
firebase deploy --only functions:<function-name>
```

#### Branches Divergenti su Git Pull

**Soluzione 1 - Merge**:
```bash
git pull origin <branch> --no-rebase
```

**Soluzione 2 - Usare versione remote**:
```bash
git fetch origin <branch>
git reset --hard origin/<branch>
```

### Comandi Utili

```bash
# Visualizzare logs functions
firebase functions:log --project creator-ai-proxy

# Logs function specifica
firebase functions:log --only routeRequest --project creator-ai-proxy

# Listare functions deployate
gcloud functions list --project creator-ai-proxy

# Verificare permessi secret
gcloud secrets get-iam-policy <SECRET_NAME> --project creator-ai-proxy

# Test function localmente
cd functions && npm run serve
```

### Link Risorse

- **Firebase Console**: https://console.firebase.google.com/project/creator-ai-proxy
- **Cloud Console**: https://console.cloud.google.com/functions/list?project=creator-ai-proxy
- **Secret Manager**: https://console.cloud.google.com/security/secret-manager?project=creator-ai-proxy

---

## Stato Attuale del Progetto

### Completato e Funzionante

#### Backend Firebase (100%)
- ✅ Sistema autenticazione JWT con licensing
- ✅ Routing AI con fallback automatico Gemini ↔ Claude
- ✅ Rate limiting per IP e license
- ✅ Cost tracking mensile per provider
- ✅ Audit logging completo
- ✅ **Plugin documentation con AI Research dinamico** (nessun fallback hardcodato)
- ✅ **Comprehensive research prompts** per documentazione completa (esempi, best practices, data structures)
- ✅ **Plugin-specific prompts** per Elementor, WooCommerce, ACF
- ✅ **Cache completeness verification** con auto-refresh per docs incompleti
- ✅ **WordPress Core API documentation** (13 topic pre-popolati con code examples e best practices)
- ✅ Middleware auth e rate limit
- ✅ 8 Cloud Functions deployate e testate
- ✅ Firestore collections configurate

#### Plugin WordPress (100%)
- ✅ Chat interface con UI completa
- ✅ **SSE Streaming**: Progressi in tempo reale via Server-Sent Events (`/chat/stream`)
- ✅ **Real-time Progress Display**: Messaggi di progresso nell'area chat (non sotto input)
- ✅ **Creator Logo**: Logo `creator-black.svg` accanto al nome "Creator" nei messaggi
- ✅ **Matching Message Widths**: Messaggi Creator e utente con stessa larghezza max (70%)
- ✅ Universal PHP Engine con security validation
- ✅ **Context injection** nel CodeExecutor per accesso a `$context` dal codice AI
- ✅ Sistema micro-step per operazioni complesse
- ✅ WP-CLI executor con whitelist/blacklist
- ✅ Context loader con lazy-loading
- ✅ Response handler per tutti i tipi di risposta
- ✅ Conversation manager con loop orchestration
- ✅ Debug panel con conversation history
- ✅ Rollback system con snapshot
- ✅ Database tables e migrations
- ✅ **Error Memory System**: Retry intelligenti che evitano approcci già falliti
- ✅ **Loop Limit 100**: Supporto task complessi fino a 100 iterazioni
- ✅ **Message Validation**: Messaggi sempre leggibili (mai JSON raw in chat)
- ✅ **JSON Parsing Robusto**: Multi-strategy parsing con fallback UTF8 e debug logging

#### Test Coverage
- ✅ 121 test cases Firebase (unit + integration)
- ✅ 44 test cases PHP plugin
- ✅ Coverage: modelService 94%, licensing 88%, auth 100%

### Architettura Finale

Il sistema è basato su un'architettura pulita e semplificata:

1. **Universal PHP Engine**: Eliminato il sistema di action handlers hardcoded, ora l'AI genera direttamente codice PHP eseguibile
2. **Simple Fallback**: Routing Gemini ↔ Claude senza matrix complessa
3. **Micro-Step System**: Per operazioni complesse con roadmap, step execution, checkpoint
4. **Context Injection**: Il codice AI ha accesso al context completo (`$context['accumulated_context']`, etc.)
5. **Dynamic Plugin Documentation**:
   - **Zero hardcoded fallbacks** - tutta la documentazione viene ricercata tramite AI
   - **Comprehensive prompts** - richiede TUTTE le funzioni, esempi completi, best practices
   - **Cache completeness check** - ri-ricerca automatica se docs incompleti
   - **Plugin-specific prompts** - istruzioni specializzate per Elementor, WooCommerce, ACF
6. **Security First**: 26+ funzioni pericolose bloccate, whitelist/blacklist per WP-CLI
7. **SSE Real-time Streaming**: Progressi in tempo reale via Server-Sent Events
   - Endpoint `GET /chat/stream` per streaming eventi
   - Eventi: `connected`, `progress`, `complete`, `error`
   - Messaggi dettagliati mostrati nell'area chat durante elaborazione
   - Utente vede "Sto configurando la Hero Section..." invece di "Creator AI is thinking..."
8. **Error Memory System**: Retry intelligenti con memoria degli errori precedenti per evitare approcci già falliti
9. **Message Validation**: Sistema `ensure_readable_message()` che garantisce messaggi user-friendly (mai JSON raw in chat)
10. **Robust JSON Parsing**: Multi-strategy parsing (diretto, markdown, brace-balanced, UTF8 fallback) con debug logging
11. **Consistent UI**: Logo Creator (`creator-black.svg`) e larghezze messaggi uniformi (70% max)
12. **File Attachments**: Upload di file allegati nella chat
    - Supporto immagini (PNG, JPEG, GIF, WebP), PDF, documenti Office, file di codice
    - Max 5 file per messaggio, max 10MB per file
    - Files inviati via POST (fallback da SSE), passati attraverso Firebase ai provider AI
    - Utile per screenshot di errori, mockup design, documenti di specifiche

### Prossimi Sviluppi Potenziali

Il sistema è completo come MVP. Eventuali sviluppi futuri potrebbero includere:

- Context caching per ridurre token usage
- Circuit breaker pattern per maggiore resilienza
- Monitoring e alerting avanzato
- Redis per rate limiting in produzione ad alto traffico
- Espansione plugin integrations con AI docs research automatico

---

*Documento generato: Dicembre 2025*  
*Versione: 3.0.0-MVP*
