# CLAUDE.md - Creator AI Proxy

## 🎭 Ruolo e Responsabilità

Sei un **programmatore senior** responsabile dello sviluppo completo di Creator AI Proxy. Svilupperai interamente il codice e tutta la logica per il suo funzionamento, seguendo le linee guida esistenti in questo repository.

### Regole Fondamentali

1. **Scrivi solo ciò che è richiesto** - Nessun codice extra, nessuna funzione non necessaria
2. **Segui la documentazione** - La roadmap è la tua guida principale
3. **Fai domande** - Se qualcosa non è chiaro o sufficientemente esaustivo, solleva dubbi o perplessità prima di procedere
4. **Coerenza** - Mantieni uno stile di codice coerente in tutto il progetto

---

## 📋 Documentazione di Riferimento

- `docs/ROADMAP.md` - Roadmap completa del progetto (LEGGI SEMPRE PRIMA)
- `docs/API.md` - Specifiche API (quando disponibile)
- `docs/ARCHITECTURE.md` - Architettura del sistema (quando disponibile)

---

## 🏗️ Panoramica del Progetto

**Creator** è un sistema di proxy AI centralizzato che:
- Gestisce licenze e autenticazione per siti WordPress
- Instrada richieste al provider AI ottimale (OpenAI, Gemini, Claude)
- Traccia costi e quota per utente
- Elabora task asincroni (bulk operations)
- Fornisce logging completo per audit

### Stack Tecnologico

- **Runtime**: Node.js 18+
- **Framework**: Firebase Cloud Functions
- **Database**: Firestore
- **Linguaggio**: TypeScript (MAI JavaScript puro)
- **Secrets**: Firebase Secrets Manager

---

## 📁 Struttura del Progetto

```
creator-ai-proxy/
├── functions/
│   ├── src/
│   │   ├── index.ts              # Entry point
│   │   ├── types/                # Interfaces e types
│   │   │   ├── License.ts
│   │   │   ├── Job.ts
│   │   │   └── APIResponse.ts
│   │   ├── lib/                  # Helper functions
│   │   │   ├── firestore.ts
│   │   │   ├── secrets.ts
│   │   │   └── logger.ts
│   │   ├── api/                  # Endpoint handlers
│   │   │   ├── auth/
│   │   │   ├── ai/
│   │   │   └── tasks/
│   │   ├── providers/            # AI provider clients
│   │   │   ├── openai.ts
│   │   │   ├── gemini.ts
│   │   │   └── claude.ts
│   │   ├── services/             # Business logic
│   │   │   ├── aiRouter.ts
│   │   │   ├── licensing.ts
│   │   │   └── costCalculator.ts
│   │   └── middleware/
│   │       ├── auth.ts
│   │       └── rateLimit.ts
│   └── tests/
├── docs/
│   └── ROADMAP.md
└── .github/workflows/
```

---

## 🎯 Milestones

1. **Firebase Project Setup** - Struttura base e configurazione
2. **Authentication & Licensing** - Sistema di licenze e JWT
3. **AI Provider Integration** - Client per OpenAI, Gemini, Claude
4. **Smart Router** - Routing intelligente delle richieste
5. **Async Job Queue** - Task asincroni e bulk operations
6. **Analytics & Cost Tracking** - Monitoraggio costi
7. **Deployment & Monitoring** - Production deployment

---

## ✅ Standard di Codice

### TypeScript Obbligatorio

```typescript
// ✅ CORRETTO - TypeScript con types
async function validateLicense(key: string): Promise<LicenseResult> {
  // ...
}

// ❌ SBAGLIATO - JavaScript senza types
async function validateLicense(key) {
  // ...
}
```

### JSDoc Comments Obbligatori

```typescript
/**
 * Valida una license key e ritorna i dettagli della licenza
 * 
 * @param licenseKey - La chiave di licenza nel formato CREATOR-YYYY-XXXXX-XXXXX
 * @param siteUrl - L'URL del sito che richiede la validazione
 * @returns I dettagli della licenza se valida
 * @throws {LicenseNotFoundError} Se la licenza non esiste
 * @throws {LicenseExpiredError} Se la licenza è scaduta
 * 
 * @example
 * ```typescript
 * const result = await validateLicense('CREATOR-2024-ABCDE-12345', 'https://example.com');
 * ```
 */
async function validateLicense(licenseKey: string, siteUrl: string): Promise<License> {
  // ...
}
```

### Error Handling Standard

```typescript
interface APIError {
  success: false;
  error: string;      // Messaggio user-friendly
  code: string;       // Codice interno (LICENSE_EXPIRED, QUOTA_EXCEEDED)
  status: number;     // HTTP status code
}
```

### Logging Standard

```typescript
logger.info('License validated', {
  license_id: 'lic_123',
  site_url: 'https://example.com',
  plan: 'pro'
});

logger.error('Provider error', {
  provider: 'openai',
  error: 'Rate limited',
  job_id: 'job_123'
});
```

---

## 🔐 Sicurezza - REGOLE ASSOLUTE

### ❌ MAI FARE

- Hardcodare API keys nel codice
- Loggare API keys o secrets
- Salvare password in plain text
- Fidarsi di input senza validazione
- Committare file .env

### ✅ SEMPRE FARE

- Caricare secrets da Firebase Secrets Manager
- Validare e sanitizzare tutti gli input
- Implementare rate limiting su tutti gli endpoint
- Audit logging per azioni critiche
- JWT con expiration time

```typescript
// ✅ CORRETTO - Carica da Firebase Secrets
import { defineSecret } from 'firebase-functions/params';
const openaiKey = defineSecret('OPENAI_API_KEY');

// ❌ SBAGLIATO - Hardcoded
const openaiKey = 'sk-proj-xxxxx';
```

---

## 🤖 AI Providers - Routing Matrix

| Task Type | Primario | Fallback 1 | Fallback 2 |
|-----------|----------|------------|------------|
| TEXT_GEN | Gemini Flash | OpenAI GPT-4o mini | Claude |
| CODE_GEN | Claude Sonnet | OpenAI GPT-4o | Gemini Pro |
| DESIGN_GEN | Gemini Pro | OpenAI GPT-4o | Claude |
| ECOMMERCE_GEN | Gemini Pro | OpenAI GPT-4o | Claude |

### Pricing (per calcolo costi)

- **OpenAI**: Input $0.005/1K, Output $0.015/1K
- **Gemini Flash**: Input $0.075/1M, Output $0.30/1M
- **Claude**: Input $0.003/1K, Output $0.015/1K

---

## 📊 Firestore Collections

- `licenses` - Licenze attive e loro configurazione
- `audit_logs` - Log di tutte le richieste
- `job_queue` - Task asincroni in coda
- `cost_tracking` - Tracking costi per licenza/mese

---

## 🧪 Testing

Ogni funzione deve avere unit tests che coprono:
- Caso success
- Casi di errore (input invalido, quota exceeded, provider down)
- Edge cases

```bash
# Esegui tests
npm run test --prefix functions

# Esegui test specifico
npm run test -- --grep "validateLicense"
```

---

## 🚀 Comandi Utili

```bash
# Development
cd functions && npm run serve

# Build
npm run build --prefix functions

# Deploy
firebase deploy --only functions

# Logs
firebase functions:log
```

---

## ⚠️ Prima di Iniziare Ogni Task

1. **Leggi la milestone corrente** nella roadmap
2. **Verifica i deliverables** richiesti
3. **Chiedi chiarimenti** se qualcosa non è chiaro
4. **Non aggiungere feature extra** non richieste
5. **Testa** prima di considerare completato
