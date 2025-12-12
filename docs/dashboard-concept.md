# Creator Dashboard - Concept Visuale

## Layout Generale

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Creator Dashboard                                          [Admin Menu]    │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────┬──────────────────────────────────┐
│                                          │                                  │
│  📊 LICENSE & ACCOUNT STATUS             │  💳 USAGE & CREDITS              │
│                                          │                                  │
│  ┌────────────────────────────────────┐ │  ┌────────────────────────────┐ │
│  │ ✅ License Active - Pro Plan       │ │  │ Credits Used                │ │
│  │                                    │ │  │                            │ │
│  │ Site: https://example.com         │ │  │ ████████░░░░░░░░░░ 45k/50k │ │
│  │ Expires: 2025-12-31 (364 days)   │ │  │                            │ │
│  │                                    │ │  │ 90% Available              │ │
│  │ [Upgrade Plan →]                   │ │  │                            │ │
│  └────────────────────────────────────┘ │  │ Reset: 2025-12-31          │ │
│                                          │  └────────────────────────────┘ │
│  System Health:                          │                                  │
│  🟢 Firebase Connected                   │                                  │
│  🟢 Gemini 2.5 Pro Active               │                                  │
│  🟢 Claude Opus 4.5 Active              │                                  │
│                                          │                                  │
└──────────────────────────────────────────┴──────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                                                                             │
│  💬 CHAT HISTORY                                     [✨ Start New Chat]   │
│                                                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ 🗨️  Create landing page for SaaS product                            🗑️│ │
│  │     Created a complete landing page with hero, features, pricing...   │ │
│  │     Today, 14:32 • 12 messages                                        │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ 🗨️  Setup WooCommerce store with custom product types              🗑️│ │
│  │     Configured WooCommerce, created variable products, set up...     │ │
│  │     Yesterday, 09:15 • 28 messages                                    │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ 🗨️  Optimize site performance and add caching                       🗑️│ │
│  │     Installed LiteSpeed Cache, configured optimization settings...   │ │
│  │     3 days ago • 15 messages                                          │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │ 🗨️  Create blog section with custom post types                      🗑️│ │
│  │     Set up custom blog layout, configured ACF fields for posts...    │ │
│  │     Dec 5, 2025 • 19 messages                                         │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
│                         [Load More Conversations]                           │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Dettagli Componenti

### 1. License & Account Status Card

**Stati Visuali:**
- ✅ `Active` (verde) - Licenza attiva
- ⚠️ `Expiring Soon` (arancione) - Meno di 30 giorni alla scadenza
- ❌ `Expired` (rosso) - Licenza scaduta
- ⏸️ `Suspended` (grigio) - Licenza sospesa

**Elementi:**
- Badge stato con icona e colore
- Piano corrente (Starter / Pro / Enterprise)
- Site URL registrato
- Data scadenza con countdown giorni
- Button "Upgrade Plan" (placeholder, non linkato per ora)
- System Health con 3 indicator:
  - Firebase Connection (🟢/🔴)
  - Gemini versione + status (🟢/🔴)
  - Claude versione + status (🟢/🔴)

### 2. Usage & Credits Card

**Elementi:**
- Titolo "Credits Used"
- Progress bar visuale (HTML5 `<progress>`)
- Numeri: `45,000 / 50,000` (format con migliaia)
- Percentuale disponibile: `90% Available`
- Data reset: `Reset: YYYY-MM-DD`

**Colori Progress Bar:**
- Verde: 0-70% usato
- Giallo: 71-90% usato
- Rosso: 91-100% usato

### 3. Chat History Panel

**Lista Conversazioni:**

Ogni riga contiene:
- Icona 🗨️ (chat bubble)
- **Titolo** (bold, 1 riga, troncato con ellipsis)
- **Riassunto** (2 righe max, troncato con ellipsis)
- **Metadata** (small text, grigio):
  - Data relativa (Today/Yesterday) o data assoluta (Dec 5, 2025)
  - Separatore bullet •
  - Numero messaggi (es. "12 messages")
- Icona 🗑️ (trash, float right, hover rosso)

**Interazioni:**
- Click su riga (escluso trash icon) → Apre chat con history caricata
- Click su trash icon → Conferma eliminazione → Hard delete
- Hover su riga → Background grigio chiaro

**Load More:**
- Pulsante centrato alla fine della lista
- Carica 10 conversazioni alla volta
- Nascosto se non ci sono più conversazioni

**Start New Chat:**
- Button primario (blu, bold)
- Posizionato in alto a destra del panel
- Icon ✨ sparkle
- Click → Redirect a chat interface con nuova conversazione

### 4. Conferma Eliminazione

Modal semplice:
```
┌─────────────────────────────────────────┐
│  Delete Conversation?                   │
├─────────────────────────────────────────┤
│                                         │
│  Are you sure you want to delete this  │
│  conversation? This action cannot be   │
│  undone.                                │
│                                         │
│  "Create landing page for SaaS..."     │
│                                         │
│         [Cancel]  [Delete]              │
│                                         │
└─────────────────────────────────────────┘
```

## Responsive Behavior

**Desktop (>1200px):**
- Layout a 2 colonne come sopra
- License & Credits side-by-side

**Tablet (768px - 1200px):**
- License & Credits stack verticalmente
- Chat History full width

**Mobile (<768px):**
- Tutto stacked verticalmente
- Cards full width
- Riduci padding/margin

## Colori e Stili

**Palette:**
- Primary: `#6366F1` (Electric Indigo)
- Success: `#10B981` (Green)
- Warning: `#F59E0B` (Orange)
- Danger: `#EF4444` (Red)
- Gray: `#6B7280` (Neutral Gray)
- Background: `#F9FAFB` (Light Gray)
- Card: `#FFFFFF` (White)

**Typography:**
- Titoli: Inter/Geist, 16-18px, 600 weight
- Body: Inter/Geist, 14px, 400 weight
- Small text: 12px, 500 weight

**Spacing:**
- Card padding: 24px
- Gap tra cards: 20px
- Gap tra conversation rows: 12px

## Animazioni

- Hover conversazione: Smooth background transition (200ms)
- Progress bar: Animated fill on load (500ms ease-out)
- Load more: Fade in nuove conversazioni (300ms)
- Delete modal: Fade in backdrop + scale modal (200ms)

## Generazione Titoli AI

**Quando:**
- Dopo 3+ messaggi in una conversazione
- Se titolo non ancora generato

**Come:**
- Chiamata API Firebase `/api/ai/generate-title`
- Payload: ultimi 3-5 messaggi della conversazione
- System prompt: "Generate a concise, descriptive title (max 60 chars) for this conversation. Focus on the main task or goal discussed."
- Salva in DB campo `title` nella tabella `conversations`

**Fallback:**
- Se API fallisce: usa prime 60 caratteri del primo messaggio utente
- Se conversazione troppo breve: "New Conversation - [timestamp]"

## Note Tecniche

**Database:**
- Conversazione `deleted_at` → NULL per attive
- Hard delete: `DELETE FROM conversations WHERE id = ?`
- Cascading delete automatico su `messages` table

**Performance:**
- Load 10 conversazioni per batch
- Cache titoli generati (no rigeneration)
- Lazy load System Health status (AJAX dopo render iniziale)

**Security:**
- Tutte le API REST richiedono `manage_options` capability
- Nonce validation su delete action
- Sanitize tutti gli output (esc_html, esc_attr)
