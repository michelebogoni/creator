# Phase 6 - Session History

Storico completo della sessione di implementazione del sistema Micro-Step per Creator.

---

## Contesto Iniziale

Prima di questa sessione erano state completate le Phases 1-5:
- Debug Panel (Phase 7)
- Fix Firebase Hosting rewrites e IAM permissions
- Fix Firestore undefined values
- Multi-turn conversation support
- Identificato problema timeout (~60 secondi limite proxy SiteGround)

---

## Richiesta 1: Analisi Costi Sistema Micro-Step

**Domanda utente**: Qual è l'impatto su costi e performance dell'approccio micro-step?

**Risposta fornita**:

| Approccio | Costo per task complesso | Affidabilità |
|-----------|-------------------------|--------------|
| Macro-task | ~$0.045 | 60% |
| Micro-step | ~$0.14 (+210%) | 95% |

**Strategie di mitigazione suggerite**:
- History compression per ridurre token
- Step caching per evitare ripetizioni
- Early termination su errori

---

## Richiesta 2: Implementazione Sistema Micro-Step

**Richiesta utente**:
> "Implementa il sistema a micro step. Per le operazioni complesse avremo quindi una sorta di roadmap e una serie di micro azioni con punti di controllo lungo il percorso."

**Requisiti specificati**:
1. Non fare distinzione Gemini/Claude per ora
2. NON limitare history a 5 messaggi - usare compressione invece ("Comprimi history strada facendo")

### Implementazione Fase 1: System Prompt

**File**: `functions/src/services/modelService.ts`

Aggiunto al system prompt:
- Sezione MICRO-STEP APPROACH con indicatori di complessità
- Nuovi response types: `roadmap`, `execute_step`, `checkpoint`, `compress_history`
- Regole per decidere quando usare roadmap

**Indicatori di complessità** (se ANY presente → usa roadmap):
- Creare pagina con multiple sezioni/elementi
- Installare o configurare multipli plugin
- Costruire layout con Elementor/page builders
- Task che menzionano "e" multiple volte
- Qualsiasi task > 30 secondi

### Implementazione Fase 2: ResponseHandler.php

**File**: `packages/creator-core-plugin/creator-core/includes/Response/ResponseHandler.php`

Aggiunti handler nel switch statement:
- `handle_roadmap_response()` - ritorna roadmap all'utente per conferma
- `handle_execute_step_response()` - esegue singolo step
- `handle_checkpoint_response()` - verifica e accumula contesto
- `handle_compress_history_response()` - comprime messaggi vecchi

### Implementazione Fase 3: ChatController.php

**File**: `packages/creator-core-plugin/creator-core/includes/Chat/ChatController.php`

Aggiunti:
- Handler per tipo `roadmap` → return immediato per conferma utente
- Handler per tipo `checkpoint` → passa accumulated_context al prossimo step
- Handler per tipo `compress_history` → sostituisce messaggi vecchi con summary
- Handler per tipo `execute_step` → esegue con step tracking
- Metodo `compress_conversation_history()` per compressione

---

## Problema 1: Roadmap Non Visibile

**Feedback utente**:
> "Ok, ma non vedo la roadmap e non ho la possibilità di cliccare per approvarla per avviare il lavoro (non ci sono pulsanti)."

**Causa**: Il backend generava correttamente la roadmap, ma il frontend non aveva UI per mostrarla.

### Fix: Frontend Updates

**File**: `packages/creator-core-plugin/creator-core/assets/js/chat-interface.js`

Aggiunti:
- Event handlers: `handleConfirmRoadmap()`, `handleCancelRoadmap()`
- `showRoadmapConfirmation()` - mostra card roadmap con lista step
- `updateRoadmapStep()` - aggiorna stato visivo (pending/executing/completed/failed)
- Handler nel success callback per checkpoint e execute_step

**File**: `packages/creator-core-plugin/creator-core/assets/css/chat-interface.css`

Aggiunti stili completi per:
- Container roadmap con bordi arrotondati e ombre
- Step individuali con numerazione
- Stati visivi: pending (grigio), executing (blu con spinner), completed (verde con check), failed (rosso con X)
- Bottoni conferma/annulla
- Design responsive

---

## Problema 2: WPCode Snippet Non Funzionante

**Feedback utente**:
> "Abbiamo un altro problema importante. Ci sono plugins come WP Code che non mettono a disposizione api o functions per essere usati da strumenti esterni... Perché l'AI non ha usato WP CLI? Quando operiamo con plugins, l'AI non deve mai agire tramite codice se non strettamente necessario, ma deve operare tramite le funzioni ufficiali che mette a disposizione il plugin."

**Cosa era successo**: Creator aveva creato uno snippet WPCode manipolando direttamente:
- Il Custom Post Type `wpcode`
- I meta fields interni (`_wpcode_snippet_*`)

**Risultato**: Lo snippet appariva nell'interfaccia WPCode ma NON funzionava perché la logica interna del plugin era stata bypassata.

### Fix: Plugin Integration Safety

**File**: `functions/src/services/modelService.ts`

Aggiunta sezione PLUGIN INTEGRATION SAFETY con regole:
1. Usare API PHP pubbliche se disponibili
2. Usare WP-CLI commands se disponibili
3. Usare hooks/filters del plugin
4. Usare REST API endpoints se esposti
5. MAI manipolare CPT/meta interni dei plugin
6. Se nessuna API esiste → dare codice all'utente per inserimento manuale via UI

---

## Richiesta 3: WP-CLI per Plugin

**Domanda utente**:
> "WP CODE però ha i WP CLI. Non sono la stessa cosa? Non possiamo usarli per fare operazioni all'interno del plugin in maniera sicura ed efficace?"

**Ricerca effettuata**: Verificato che WPCode potrebbe avere comandi WP-CLI, ma Creator non poteva usare `shell_exec` per ragioni di sicurezza.

**Soluzione proposta**: Creare WPCLIExecutor.php con sistema whitelist/blacklist per esecuzione sicura.

---

## Richiesta 4: Implementazione WPCLIExecutor

**Richiesta utente**:
> "Ottimo, si implementa WPCLIExecutor.php."

### Implementazione WPCLIExecutor

**File creato**: `packages/creator-core-plugin/creator-core/includes/Execution/WPCLIExecutor.php`

**Caratteristiche**:

**Whitelist comandi permessi**:
```
wp post, wp page, wp media, wp menu, wp widget, wp sidebar, wp comment
wp term, wp taxonomy
wp user list, wp user get, wp user meta (solo lettura)
wp option get/list/update/add
wp plugin list/get/is-installed/is-active/path (solo lettura)
wp theme list/get/is-installed/is-active/path (solo lettura)
wp transient, wp cache flush/get/set
wp rewrite flush/list, wp cron event list/run
wp wc, wp acf, wp elementor, wp wpcode, wp code-snippets
```

**Blacklist pattern bloccati**:
```
--allow-root, eval, eval-file, shell
db drop, db reset, site delete
plugin install/delete/update
theme install/delete/update
user create/delete/update
config, package, server
> (redirect), | (pipe), ; && || (chaining), ` $( (substitution)
```

**Sicurezza**:
- Timeout massimo 30 secondi
- Auto-detection path WP-CLI
- Output JSON parsing
- Escape sicuro argomenti

### Integrazione nel sistema

**ResponseHandler.php**: Aggiunto `handle_wp_cli_response()` con esecuzione e retry logic

**ChatController.php**: Aggiunto handler con:
- Verifica disponibilità WP-CLI
- Retry logic se fallisce
- Fallback a istruzioni manuali se WP-CLI non disponibile

---

## Problema 3: Liste Plugin Hardcoded

**Feedback utente CRITICO**:
> "Voglio anche accertarmi di una cosa: sulla sezione PLUGIN INTEGRATION SAFETY che hai creato non specifichiamo il nome di plugin ma invitiamo l'ai a verificare se quel plugin/versione prevede api o wp cli. Seguiamo sempre la strada del 'no hardcoded' perché se domani WP Code implementasse le api e noi abbiamo hardcodato 'non usare wp code perché non ha le api' siamo fregati."

**Errore commesso**: Avevo scritto liste tipo:
- "WPCode - Give code to paste manually"
- "Rank Math - Use rm_..."
- etc.

**Problema**: Se un plugin aggiunge API in futuro, le istruzioni hardcoded sarebbero obsolete.

### Fix: Sistema Dinamico

**File**: `functions/src/services/modelService.ts`

Rimosso TUTTE le liste hardcoded. Sostituito con decision flow dinamico:

```
Task involves a plugin?
    ↓
Request plugin documentation (type: "request_docs")
    ↓
Documentation shows public API/WP-CLI?
    ├─ YES → Use the official API or WP-CLI command
    └─ NO  → Tell user to configure manually via plugin UI
```

**Regole chiave**:
1. SEMPRE richiedere documentazione PRIMA di operare su un plugin
2. Controllare docs per: PHP APIs, WP-CLI, hooks/filters
3. MAI assumere - sempre verificare
4. Se nessuna API dopo verifica → istruzioni manuali

---

## Richiesta 5: Report Tecnico

**Richiesta utente**:
> "Ora scrivi un file di report md che contiene tutto quello che abbiamo imparato e quello che abbiamo implementato lungo tutta questa chat."

**File creato**: `docs/PHASE-6-MICRO-STEP-IMPLEMENTATION.md` (700+ righe con diagrammi ASCII)

---

## Problema 4: Formato Report Sbagliato

**Feedback utente**:
> "Elimina quel file di report. Non mi interessa il report specifico sul diagramma di flusso. Mi interessa avere uno storico di tutta questa chat: tutta la conversazione e le implementazioni che abbiamo fatto."

**Azione**: File eliminato.

**Nuovo approccio**: Questo documento - formato cronologico/storico invece di diagrammi tecnici.

---

## Riepilogo Modifiche per File

### Files Modificati

| File | Modifiche |
|------|-----------|
| `functions/src/services/modelService.ts` | System prompt: MICRO-STEP APPROACH, PLUGIN INTEGRATION SAFETY, nuovi response types |
| `includes/Response/ResponseHandler.php` | Handler per roadmap, execute_step, checkpoint, compress_history, wp_cli |
| `includes/Chat/ChatController.php` | Loop handling per nuovi tipi, compress_conversation_history() |
| `assets/js/chat-interface.js` | UI roadmap, event handlers, step tracking |
| `assets/css/chat-interface.css` | Stili roadmap e stati step |

### Files Creati

| File | Scopo |
|------|-------|
| `includes/Execution/WPCLIExecutor.php` | Esecuzione sicura comandi WP-CLI |

---

## Lezioni Apprese

1. **No hardcoding di comportamenti plugin-specifici**: L'AI deve sempre verificare la documentazione corrente

2. **Mai manipolare internals dei plugin**: Usare sempre API ufficiali, WP-CLI, o dare istruzioni manuali

3. **Frontend e backend devono essere sincronizzati**: Nuovi response types richiedono UI corrispondente

4. **Compressione > Troncamento**: Meglio comprimere history con summary che tagliare messaggi

5. **Whitelist + Blacklist per sicurezza**: Doppio livello di protezione per comandi shell

---

## Richiesta 6: Raffinamento Decision Flow

**Richiesta utente**:
> "Se sono presenti delle API o WP-CLI documentate per svolgere per determinato compito, allora le usiamo... Se invece il plugin non offre delle API o WP-CLI pubbliche... informiamo l'utente proponendogli due strade... Se invece abbiamo un caso intermedio..."

**Problema identificato**: Il decision flow precedente era troppo semplice (solo YES/NO), non gestiva il caso intermedio dove le API coprono solo PARTE del task.

### Fix: Decision Flow a 3 Scenari

**File**: `functions/src/services/modelService.ts`

Riscritta completamente la sezione decisionale con 3 scenari:

**SCENARIO A - Full Coverage**: Le API/WP-CLI coprono TUTTO il task
→ Usa API/WP-CLI e completa automaticamente

**SCENARIO B - No Coverage**: Nessuna API/WP-CLI per il task
→ Proponi 2 opzioni all'utente:
  1. Approccio alternativo (senza quel plugin)
  2. Istruzioni manuali per la UI del plugin

**SCENARIO C - Partial Coverage**: API/WP-CLI coprono solo PARTE del task
→ Approccio misto:
  1. Completa automaticamente le parti supportate
  2. Istruzioni manuali o alternativa per il resto

**Esempi aggiunti**:
- Scenario A: "List all WooCommerce products" → WP-CLI completo
- Scenario B: "Create snippet" (plugin senza API) → 2 opzioni all'utente
- Scenario C: "Create product with custom meta" → mix automatico + manuale

---

## Stato Attuale

**Completato**:
- Sistema micro-step (backend)
- UI roadmap (frontend)
- Plugin Integration Safety dinamico (con 3 scenari)
- WPCLIExecutor con sicurezza

**Da testare**:
- Flow completo micro-step con task reale
- Disponibilità WP-CLI su SiteGround
- Compressione history in produzione

---

## Prossimi Passi

1. Deploy Firebase functions con nuove modifiche system prompt
2. Test end-to-end del sistema roadmap
3. Verifica WP-CLI availability su hosting
