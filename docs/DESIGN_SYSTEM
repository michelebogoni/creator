# Creator - Style Guidelines

**Versione:** 1.0  
**Data:** Dicembre 2025  
**Filosofia:** Minimal • Geometrico • Monocromatico • Moderno

---

## Filosofia di Design

Creator adotta un approccio **minimal-geometrico** che comunica professionalità attraverso forme pulite e un uso esclusivo della scala di grigi.

### Principi Chiave

1. **Solo scala di grigi** - Nessun colore, solo bianco/nero/grigi
2. **Forme rotonde** - Angoli arrotondati su tutto (cards, buttons, inputs)
3. **Spazio negativo** - Il vuoto è parte del design, generoso padding
4. **Geometria precisa** - Allineamenti perfetti, griglia coerente
5. **Contrasto sottile** - Gerarchia tramite peso, dimensione, opacità

---

## Palette Colori

**Uso esclusivo di scala di grigi.**

### Grigi Base

- **Grigi chiari** (#F5F5F5 - #E5E5E5) - Background, cards, aree contenuto
- **Grigi medi** (#A3A3A3 - #737373) - Borders, testo secondario, placeholders
- **Grigi scuri** (#404040 - #262626) - Testo primario, headings, elementi enfatizzati
- **Bianco/Nero** (#FFFFFF / #000000) - Background cards principali, contrasto massimo

### Applicazione

- **Background**: Grigi molto chiari per pagine, bianco per cards
- **Testo**: Grigi scuri per leggibilità, mai nero puro
- **Borders**: Grigi chiari/medi, più scuri su hover
- **Stati interattivi**: Hover/focus con grigi leggermente più scuri
- **Overlay modals**: Nero semi-trasparente (50% opacity)

**No colori.** Stati di successo/errore/warning comunicati via text/icone, non colori.

---

## Typography

### Font

**Inter** come font primario per la sua chiarezza geometrica.

Fallback a system fonts geometrici: `-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`

### Scala Tipografica

Basata su `em` per scalabilità e accessibilità:

- **Small** (0.75em - 0.875em) - Meta info, timestamp, label
- **Base** (1em) - Body text standard
- **Medium** (1.125em - 1.25em) - Sottotitoli, lead
- **Large** (1.5em - 2em) - Titoli sezioni e pagine

### Pesi

- **Normal** (400) - Body text
- **Medium** (500) - Text enfatizzato
- **Semibold** (600) - Titoli cards, buttons
- **Bold** (700) - Headings principali

### Line Height

- **Tight** (~1.25) - Titoli
- **Normal** (~1.5) - Body text
- **Relaxed** (~1.75) - Long-form text

### Applicazione Generale

- Titoli: Font più grande, peso bold/semibold, grigio molto scuro
- Body: Dimensione base, peso normal, grigio scuro
- Secondario: Dimensione più piccola, peso medium, grigio medio
- Meta/timestamp: Dimensione small, grigio chiaro

---

## Spacing & Layout

### Spacing

Usa `em` per spacing responsive e scalabile:

- **Tight** (0.25em - 0.5em) - Gap tra elementi inline
- **Standard** (0.75em - 1em) - Padding interno elementi, gap tra componenti
- **Generous** (1.5em - 2em) - Padding cards, separazione sezioni
- **Extra** (3em - 4em) - Spacing tra sezioni maggiori

### Layout

- **Cards/Containers**: Background bianco o grigio chiaro, padding generoso (1.5em)
- **Grid**: 2 colonne desktop, stack su mobile
- **Max-width**: Contenuti centrati con max-width ragionevole (~1200px)
- **Whitespace**: Abbondante, non affollare l'interfaccia

---

## Forme & Elevation

### Border Radius

Angoli arrotondati su **tutto** per coerenza geometrica:

- **Small** (0.25em - 0.5em) - Badge, tag, piccoli elementi
- **Medium** (0.5em - 0.75em) - Buttons, inputs, cards standard
- **Large** (1em - 1.5em) - Cards grandi, modals
- **Full** (999em) - Elementi circolari (avatar, badge)

### Shadows

Usa ombre sottili per creare depth senza colori:

- **Subtle** - Cards a riposo, leggera elevazione
- **Medium** - Cards hover, elementi fluttuanti
- **Strong** - Modals, dropdown, massima elevazione

Ombre sempre nere con bassa opacità (5-25%).

---

## Componenti

### Principi Generali

**Buttons:**
- Background grigio scuro, testo bianco
- Border radius medio, padding generoso
- Hover: grigio più scuro + leggera elevazione
- Secondary: outline grigio invece di fill

**Cards:**
- Background bianco, bordo grigio chiaro
- Border radius large, padding generoso
- Shadow sottile a riposo, shadow medium su hover
- Se clickable: hover con background grigio leggerissimo

**Inputs:**
- Background bianco, bordo grigio medio
- Border radius medio, padding standard
- Focus: bordo grigio scuro + subtle shadow
- Placeholder: grigio chiaro

**Badges:**
- Background grigio scuro, testo bianco
- Border radius full per forma pill
- Dimensione small, padding tight
- O variant outline con bordo grigio

**Progress Bar:**
- Container: grigio chiaro, border radius full
- Fill: grigio scuro (mai colori dinamici)
- Animazione smooth su cambio valore

**Message Bubbles (Chat):**
- User: grigio scuro background, testo bianco, allineato destra
- Assistant: bianco background con bordo grigio, allineato sinistra
- Border radius con "tail" (un angolo meno arrotondato verso il lato)

---

## Iconografia

### Icona Menu

Simbolo geometrico a metà tra "C" e "<", in grassetto.

Forma: Chevron sinistro arrotondato con stroke spesso (2.5-3px).

### Icone Generali

- Stile geometrico minimal (es. Lucide Icons)
- Stroke arrotondato (linecap round)
- Colori: grigi dalla palette
- Dimensioni: small (1em), medium (1.25em), large (1.5em - 2em)

---

## Interazioni

### Stati

- **Hover**: Background leggermente più scuro, bordo più definito
- **Focus**: Outline grigio visibile per accessibilità
- **Active/Pressed**: Leggero scale down (transform: scale(0.98))
- **Disabled**: Opacità ridotta (50%), cursor not-allowed

### Transizioni

- **Durata standard**: 200ms
- **Easing**: ease-out per naturalezza
- **Proprietà**: background, border, shadow, transform
- **No animazioni eccessive**: sottile e professionale

---

## Responsive

### Breakpoints

- **Mobile**: < 768px - Stack tutto verticalmente
- **Tablet**: 768px - 1024px - Layout intermedio
- **Desktop**: > 1024px - Grid 2 colonne, layout full

### Adattamenti

- Padding ridotto su mobile
- Font size base leggermente più grande su mobile per leggibilità
- Buttons full-width su mobile se necessario
- Modals occupano 90% viewport su mobile

---

## Applicazione Specifica

### Dashboard

- **Cards top**: 2 colonne desktop, stack mobile
- **License status**: Badge grigio con emoji/simbolo, no colori stato
- **Usage progress**: Barra grigia sempre, percentuale text per info
- **Chat history**: Lista con hover effect sottile, click intera riga
- **Delete**: Icona grigia, hover grigio scuro (no rosso)

### Chat

- **Container**: Background grigio leggerissimo, max-width centrato
- **Messages area**: Scroll area con padding generoso
- **Input area**: Fixed bottom, background bianco con bordo top
- **Typing indicator**: Dots grigi animati in bubble bianco
- **Send button**: Grigio scuro, posizionato inline con input

---

## Note Implementazione

- **Load Inter font** da Google Fonts o host locally
- **CSS Variables** per tutti i valori riutilizzabili
- **Usa em** invece di px per tutto lo spacing/sizing
- **Mobile-first** approach con media queries
- **Accessibilità**: Contrasto sufficiente (WCAG AA), focus visibili
- **Performance**: Transizioni solo su proprietà performanti (transform, opacity)

---

*Style Guidelines Creator - Minimal • Geometrico • Monocromatico*
