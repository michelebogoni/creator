# Firebase Architecture - Creator AI Proxy

## Overview

Creator AI Proxy è un sistema Firebase che gestisce:
- Autenticazione licenze WordPress
- Routing richieste AI con fallback automatico
- Cache centralizzata documentazione plugin
- Tracking costi e utilizzo

---

## Cloud Functions

### Funzioni Esportate (8 totali)

| Funzione | Metodo | Endpoint | Descrizione |
|----------|--------|----------|-------------|
| `validateLicense` | POST | `/api/auth/validate-license` | Valida licenza e genera JWT |
| `routeRequest` | POST | `/api/ai/route-request` | Routing richieste AI |
| `getPluginDocsApi` | GET | `/api/plugin-docs/:slug/:version` | Recupera docs plugin dalla cache |
| `savePluginDocsApi` | POST | `/api/plugin-docs` | Salva docs plugin in cache |
| `getPluginDocsStatsApi` | GET | `/api/plugin-docs/stats` | Statistiche repository |
| `getPluginDocsAllVersionsApi` | GET | `/api/plugin-docs/all/:slug` | Tutte le versioni di un plugin |
| `researchPluginDocsApi` | POST | `/api/plugin-docs/research` | Ricerca docs con AI |
| `syncPluginDocsApi` | POST | `/api/plugin-docs/sync` | Sync docs per WordPress |

### Regioni

| Funzione | Regione | Generazione |
|----------|---------|-------------|
| `routeRequest` | us-central1 | Gen2 |
| `validateLicense` | europe-west1 | Gen2 |
| Plugin Docs (tutte) | us-central1 | Gen1 |

---

## Firestore Collections

### 1. `licenses`

Licenze utente e quote.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `license_key` | string | Formato: `CREATOR-YYYY-XXXXX-XXXXX` |
| `site_url` | string | URL sito WordPress registrato |
| `site_token` | string | JWT token per autenticazione |
| `user_id` | string | ID proprietario |
| `plan` | string | `starter` \| `pro` \| `enterprise` |
| `tokens_limit` | number | Limite token mensile |
| `tokens_used` | number | Token utilizzati |
| `status` | string | `active` \| `suspended` \| `expired` |
| `reset_date` | Timestamp | Data reset quota |
| `expires_at` | Timestamp | Scadenza licenza |
| `created_at` | Timestamp | Data creazione |
| `updated_at` | Timestamp | Ultimo aggiornamento |

### 2. `audit_logs`

Log di tutte le richieste per compliance.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `license_id` | string | ID licenza |
| `request_type` | string | `license_validation` \| `ai_request` \| `task_submission` |
| `provider_used` | string | `openai` \| `gemini` \| `claude` |
| `tokens_input` | number | Token input |
| `tokens_output` | number | Token output |
| `cost_usd` | number | Costo in USD |
| `status` | string | `success` \| `failed` \| `timeout` |
| `ip_address` | string | IP client |
| `response_time_ms` | number | Tempo risposta |
| `metadata` | object | Dati aggiuntivi |
| `created_at` | Timestamp | Data richiesta |

### 3. `rate_limit_counters`

Contatori rate limiting per IP.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `endpoint` | string | Endpoint limitato |
| `ip_address` | string | IP client |
| `hour` | number | Bucket temporale |
| `count` | number | Contatore richieste |
| `ttl` | Timestamp | Auto-delete dopo 2 minuti |

### 4. `cost_tracking`

Tracking costi mensili per licenza.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `license_id` | string | ID licenza |
| `month` | string | Formato: `YYYY-MM` |
| `openai_tokens_input` | number | Token input OpenAI |
| `openai_tokens_output` | number | Token output OpenAI |
| `openai_cost_usd` | number | Costo OpenAI |
| `gemini_tokens_input` | number | Token input Gemini |
| `gemini_tokens_output` | number | Token output Gemini |
| `gemini_cost_usd` | number | Costo Gemini |
| `claude_tokens_input` | number | Token input Claude |
| `claude_tokens_output` | number | Token output Claude |
| `claude_cost_usd` | number | Costo Claude |
| `total_cost_usd` | number | Costo totale |

### 5. `plugin_docs_cache`

Cache centralizzata documentazione plugin WordPress.

**NOTA IMPORTANTE - Version Matching X.Y:**
Le versioni sono normalizzate al formato X.Y (es: `6.2.5` → `6.2`). Questo riduce la frammentazione della cache - gli aggiornamenti patch non richiedono nuova ricerca AI.

| Campo | Tipo | Descrizione |
|-------|------|-------------|
| `plugin_slug` | string | Es: `advanced-custom-fields` |
| `plugin_version` | string | Formato X.Y, es: `6.2` (normalizzato da `6.2.5`) |
| `docs_url` | string | URL documentazione ufficiale |
| `functions_url` | string | URL reference funzioni |
| `main_functions` | string[] | Funzioni principali con signature |
| `api_reference` | string | URL API reference |
| `version_notes` | string[] | Note versione |
| `description` | string | Descrizione dettagliata plugin |
| `code_examples` | string[] | Esempi di codice funzionanti |
| `best_practices` | string[] | Best practices e linee guida |
| `data_structures` | string[] | Strutture dati (meta keys, JSON schemas) |
| `component_types` | object[] | Tipi componenti per page builders |
| `cached_at` | Timestamp | Data cache |
| `cache_hits` | number | Contatore utilizzi |
| `source` | string | `ai_research` \| `manual` |
| `research_meta` | object | Metadati ricerca AI |

---

## AI Providers

### Modelli Configurati

| Provider | Model ID | Input $/1k | Output $/1k |
|----------|----------|------------|-------------|
| **Claude** | `claude-opus-4-5-20251101` | $0.015 | $0.075 |
| **Gemini** | `gemini-2.5-pro` | $0.00125 | $0.005 |

### Strategia Routing

```
DEFAULT_ROUTING_MATRIX = {
  TEXT_GEN:      { primary: claude, fallback: gemini },
  CODE_GEN:      { primary: claude, fallback: gemini },
  DESIGN_GEN:    { primary: claude, fallback: gemini },
  ECOMMERCE_GEN: { primary: claude, fallback: gemini }
}
```

Se Claude fallisce, automaticamente usa Gemini come fallback.

---

## Flussi Principali

### 1. Validazione Licenza

```
WordPress Plugin
      ↓
POST /api/auth/validate-license
      ↓
Rate Limit Check (10 req/min per IP)
      ↓
Fetch License da Firestore
      ↓
Valida: status, expiration, URL match, quota
      ↓
Genera/Riutilizza JWT (site_token)
      ↓
Log in audit_logs
      ↓
Ritorna JWT + info licenza
```

### 2. Richiesta AI

```
WordPress Plugin (con JWT)
      ↓
POST /api/ai/route-request
      ↓
Autentica JWT
      ↓
Rate Limit (100 req/min per licenza)
      ↓
Valida request body
      ↓
ModelService.generate()
  ├→ Prova Claude (primario)
  └→ Fallback a Gemini se fallisce
      ↓
Aggiorna tokens_used in licenses
      ↓
Aggiorna cost_tracking (mensile)
      ↓
Log in audit_logs
      ↓
Ritorna risposta AI
```

### 3. Ricerca Plugin Docs

```
POST /api/plugin-docs/research
      ↓
Normalizza versione a X.Y (es: 3.34.0 → 3.34)
      ↓
Check cache (plugin_docs_cache)
      ↓
Se cache miss:
  └→ Usa Claude per ricerca AI completa
      ↓
Salva risultato in cache (con versione X.Y)
      ↓
Ritorna documentazione
```

---

## Secrets (Google Secret Manager)

| Nome | Descrizione |
|------|-------------|
| `JWT_SECRET` | Secret per firma JWT |
| `GEMINI_API_KEY` | API key Google Gemini |
| `CLAUDE_API_KEY` | API key Anthropic Claude |

---

## Rate Limiting

| Endpoint | Limite | Bucket |
|----------|--------|--------|
| `/api/auth/validate-license` | 10 req/min | Per IP |
| `/api/ai/route-request` | 100 req/min | Per licenza |

---

## Tipi di Task AI

| Task Type | Descrizione |
|-----------|-------------|
| `TEXT_GEN` | Generazione testo generico |
| `CODE_GEN` | Generazione codice |
| `DESIGN_GEN` | Generazione design/layout |
| `ECOMMERCE_GEN` | Generazione contenuti e-commerce |

---

## Struttura Directory

```
functions/
├── src/
│   ├── index.ts                 # Export funzioni
│   ├── api/
│   │   ├── ai/
│   │   │   └── routeRequest.ts  # Endpoint AI principale
│   │   ├── auth/
│   │   │   └── validateLicense.ts
│   │   └── plugin-docs/
│   │       └── pluginDocs.ts    # 6 endpoint plugin docs
│   ├── services/
│   │   ├── modelService.ts      # Logica AI con fallback
│   │   ├── licensing.ts         # Validazione licenze
│   │   └── pluginDocsResearch.ts # Ricerca docs con AI
│   ├── providers/
│   │   ├── gemini.ts            # Provider Gemini (fallback)
│   │   └── claude.ts            # Provider Claude (primario)
│   ├── lib/
│   │   ├── firestore.ts         # Operazioni database + normalizePluginVersion()
│   │   ├── jwt.ts               # Gestione JWT
│   │   ├── secrets.ts           # Definizione secrets
│   │   └── logger.ts            # Logging
│   ├── middleware/
│   │   ├── auth.ts              # Autenticazione JWT
│   │   └── rateLimit.ts         # Rate limiting
│   ├── config/
│   │   └── models.ts            # Configurazione modelli AI
│   ├── scripts/
│   │   └── cleanupPluginDocsCache.ts  # Cleanup cache vecchie versioni X.Y.Z
│   ├── types/
│   │   ├── License.ts
│   │   ├── Route.ts
│   │   ├── PluginDocs.ts
│   │   └── ...
│   └── utils/
│       └── promptUtils.ts       # Validazione prompt
├── .eslintrc.js
├── package.json
├── package-lock.json
└── tsconfig.json
```

---

## Context WordPress

Il contesto WordPress viene passato nelle richieste AI:

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
    { name: "WooCommerce", version: "8.5.0" },
    // ...
  ]
}
```

Questo contesto viene:
1. Loggato per debug
2. Aggiunto al system prompt dell'AI
3. Inserito come header nel prompt utente: `[SITE INFO: WP 6.9 | PHP 8.2.29 | Theme: Hello Elementor | 12 plugins]`
