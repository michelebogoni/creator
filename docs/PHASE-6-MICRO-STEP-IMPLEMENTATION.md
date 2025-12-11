# Phase 6: Micro-Step System Implementation

## Overview

This document describes the implementation of the **Micro-Step System** for Creator, designed to handle complex WordPress tasks reliably by breaking them into small, atomic operations with checkpoints.

**Problem Solved**: Complex tasks (like building pages with multiple sections) were timing out due to managed WordPress hosting proxy limits (~60 seconds). A single AI response generating large code blocks would exceed this limit.

**Solution**: Break complex tasks into micro-steps (max 30 seconds each), with progress tracking and automatic continuation.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [New Response Types](#new-response-types)
3. [Plugin Integration Safety](#plugin-integration-safety)
4. [WP-CLI Executor](#wp-cli-executor)
5. [Conversation History Compression](#conversation-history-compression)
6. [Frontend Roadmap UI](#frontend-roadmap-ui)
7. [Files Modified](#files-modified)
8. [Cost Analysis](#cost-analysis)

---

## Architecture Overview

### Micro-Step Flow

```
User: "Create landing page with hero, features, and CTA"
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│  AI analyzes task complexity                            │
│  COMPLEXITY INDICATORS detected:                        │
│  - Multiple sections (hero, features, CTA)              │
│  - Page building with Elementor                         │
│  - Task mentions "and" multiple times                   │
└─────────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│  type: "roadmap"                                        │
│  Creates step-by-step plan with atomic operations       │
│  User sees roadmap UI with "Avvia Roadmap" button       │
└─────────────────────────────────────────────────────────┘
    │
    ▼ User confirms
    │
┌─────────────────────────────────────────────────────────┐
│  LOOP: For each step in roadmap                         │
│                                                         │
│  1. type: "execute_step" - Run atomic code (~10-20s)    │
│     │                                                   │
│     ▼                                                   │
│  2. type: "checkpoint" - Verify success, save context   │
│     accumulated_context: { page_id: 155 }               │
│     │                                                   │
│     ▼                                                   │
│  3. Continue to next step with context                  │
│                                                         │
└─────────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│  type: "complete"                                       │
│  All steps done, task completed                         │
└─────────────────────────────────────────────────────────┘
```

### Key Principles

1. **Atomic Steps**: Each step does ONE thing (create page, add section, configure widget)
2. **Max 30 Seconds**: No single step should take more than 30 seconds
3. **Checkpoints**: After each step, verify success before continuing
4. **Accumulated Context**: Pass data between steps (e.g., page_id created in step 1 used in step 2)
5. **Retry Logic**: If a step fails, retry with different approach (max 3 retries)

---

## New Response Types

### 1. `roadmap` - Task Planning

Used when AI detects a complex task. Creates a visual roadmap for user confirmation.

```json
{
  "step": "strategy",
  "type": "roadmap",
  "status": "Roadmap ready",
  "message": "Ho creato una roadmap per completare il task:",
  "data": {
    "roadmap_id": "roadmap-1749220691",
    "title": "Pagina Presentazione Creator con Hero Section",
    "total_steps": 3,
    "estimated_time": "1-2 minuti",
    "steps": [
      {
        "index": 1,
        "title": "Creare la pagina base",
        "description": "Creo una nuova pagina WordPress configurata per Elementor Canvas",
        "atomic": true
      },
      {
        "index": 2,
        "title": "Caricare immagine di background",
        "description": "Carico un'immagine placeholder per il background della Hero Section",
        "atomic": true
      },
      {
        "index": 3,
        "title": "Costruire Hero Section",
        "description": "Creo la Hero Section full-width con titolo, sottotitolo e CTA",
        "atomic": true
      }
    ]
  },
  "requires_confirmation": true,
  "continue_automatically": false
}
```

**Complexity Indicators** (triggers roadmap creation):
- Creating a page with multiple sections/elements
- Installing or configuring multiple plugins
- Building layouts with Elementor/page builders
- Tasks that mention "and" multiple times
- Any task that would take > 30 seconds to execute

### 2. `execute_step` - Atomic Execution

Executes a single step from the roadmap. Code must be small and focused.

```json
{
  "step": "implementation",
  "type": "execute_step",
  "status": "Step 1/3: Creating page...",
  "message": "Creo la pagina base...",
  "data": {
    "roadmap_id": "roadmap-1749220691",
    "step_index": 1,
    "step_title": "Create base page",
    "total_steps": 3,
    "code": "$page_id = wp_insert_post(['post_title' => 'Landing Page', 'post_type' => 'page', 'post_status' => 'draft']); return ['success' => true, 'page_id' => $page_id];"
  },
  "requires_confirmation": false,
  "continue_automatically": true
}
```

**Rules**:
- Code blocks must be < 50 lines
- Must return result array with `success` boolean
- Single responsibility per step

### 3. `checkpoint` - Progress Tracking

Reports progress after each step. Contains accumulated context for next steps.

```json
{
  "step": "implementation",
  "type": "checkpoint",
  "status": "Step 1/3 complete ✓",
  "message": "Pagina creata con successo (ID: 155). Procedo con il prossimo step.",
  "data": {
    "roadmap_id": "roadmap-1749220691",
    "completed_step": 1,
    "total_steps": 3,
    "step_result": { "success": true, "page_id": 155 },
    "progress_percentage": 33,
    "next_step": 2,
    "accumulated_context": {
      "page_id": 155
    }
  },
  "requires_confirmation": false,
  "continue_automatically": true
}
```

**Key Fields**:
- `accumulated_context`: Data passed to subsequent steps
- `progress_percentage`: Visual progress indicator
- `next_step`: Which step to execute next

### 4. `wp_cli` - WP-CLI Command Execution

Executes WP-CLI commands when plugins support them.

```json
{
  "step": "implementation",
  "type": "wp_cli",
  "status": "Executing WP-CLI...",
  "message": "Eseguo il comando WP-CLI per creare lo snippet...",
  "data": {
    "command": "wpcode snippet create --title='Hide Admin Bar' --code='add_filter(show_admin_bar, __return_false);' --type=php --status=active",
    "description": "Create code snippet via WPCode CLI"
  },
  "requires_confirmation": false,
  "continue_automatically": true
}
```

### 5. `compress_history` - Memory Optimization

Compresses old conversation history while preserving key facts.

```json
{
  "step": "discovery",
  "type": "compress_history",
  "status": "Compressing history...",
  "message": "Compressing conversation history to optimize performance.",
  "data": {
    "summary": "User requested landing page with hero section. Page created (ID: 155). Currently working on adding features section.",
    "key_facts": [
      { "key": "page_id", "value": 155, "description": "Main landing page" },
      { "key": "current_step", "value": 3, "description": "Adding features section" }
    ],
    "preserve_last_messages": 4
  },
  "requires_confirmation": false,
  "continue_automatically": true
}
```

**How it works**:
1. AI detects conversation is getting long (>10 messages)
2. AI generates summary of important context
3. Old messages replaced with summary
4. Recent messages (last 4) preserved intact
5. Key facts (IDs, current state) explicitly saved

---

## Plugin Integration Safety

### The Problem

When AI manipulates plugin internals directly (inserting posts into plugin CPTs, setting meta fields), the functionality appears to work but is actually broken because:

1. Plugin initialization/validation logic is skipped
2. Internal caches are not updated
3. Plugin-specific hooks don't fire

**Example of BROKEN approach**:
```php
// WRONG: Direct database manipulation
$snippet_id = wp_insert_post(['post_type' => 'wpcode', ...]);
update_post_meta($snippet_id, '_wpcode_snippet_active', '1');
// Result: Snippet visible in UI but NOT functional
```

### The Solution: Dynamic API Discovery

The system prompt now enforces a **dynamic approach** - no hardcoded lists of plugins.

**Decision Flow**:
```
Task involves a plugin?
    │
    ▼
Request plugin documentation (type: "request_docs")
    │
    ▼
Documentation shows public API/WP-CLI?
    ├─ YES → Use the official API or WP-CLI command
    │
    └─ NO  → Tell user to configure manually via plugin UI
             Provide the code/settings they need to enter
```

### Rules (from System Prompt)

1. **ALWAYS request documentation FIRST**
   - Before operating on ANY plugin, use `type: "request_docs"`
   - Check documentation for: public PHP APIs, WP-CLI commands, hooks/filters
   - NEVER assume a plugin has or doesn't have APIs

2. **Use official integration methods** (in order of preference):
   1. Public PHP API
   2. WP-CLI commands
   3. Hooks/Filters
   4. REST API endpoints

3. **NEVER manipulate plugin internals directly**
   - DO NOT insert/update posts using plugin's internal CPT
   - DO NOT manipulate meta fields (prefixed with underscore like `_plugin_*`)
   - DO NOT bypass plugin logic by writing directly to database

4. **If no official API exists**:
   - Inform user the plugin doesn't expose public APIs
   - Provide code/configuration for manual entry via plugin UI
   - NEVER try to reverse-engineer or bypass intended usage

### Why No Hardcoded Lists?

We explicitly avoid lists like "WPCode has no API" because:
- Plugins evolve - WPCode could add API tomorrow
- New plugins appear constantly
- Version differences may have different capabilities

The AI must **always verify** by checking documentation.

---

## WP-CLI Executor

### Overview

`WPCLIExecutor.php` provides safe execution of WP-CLI commands from within WordPress.

**Location**: `packages/creator-core-plugin/creator-core/includes/Execution/WPCLIExecutor.php`

### Security Model

**Whitelist Approach** - Only commands starting with allowed prefixes can execute:

```php
private const ALLOWED_PREFIXES = [
    // Content management
    'wp post', 'wp page', 'wp media', 'wp menu', 'wp widget',

    // Taxonomy
    'wp term', 'wp taxonomy',

    // Users (limited)
    'wp user list', 'wp user get', 'wp user meta',

    // Options
    'wp option get', 'wp option list', 'wp option update',

    // Plugins (read-only)
    'wp plugin list', 'wp plugin get', 'wp plugin is-installed',

    // Plugin-specific
    'wp wc',           // WooCommerce
    'wp acf',          // ACF
    'wp elementor',    // Elementor
    'wp wpcode',       // WPCode
];
```

**Blacklist for Extra Safety** - These patterns are NEVER allowed:

```php
private const BLOCKED_PATTERNS = [
    '--allow-root', '--skip-themes', '--skip-plugins',
    'eval', 'eval-file', 'shell',
    'db drop', 'db reset', 'site delete',
    'plugin install', 'plugin delete', 'plugin update',
    'user create', 'user delete',
    '> ', '| ', '; ', '&& ', '`', '$(',
];
```

### Features

| Feature | Description |
|---------|-------------|
| **Auto-detect** | Finds WP-CLI path automatically |
| **Timeout** | Max 30 seconds per command |
| **JSON output** | Parses WP-CLI JSON responses |
| **Graceful fallback** | Returns error if WP-CLI unavailable |

### Usage Flow

```
AI: type "wp_cli" with command
    │
    ▼
WPCLIExecutor.execute(command)
    │
    ├─ Validate against whitelist/blacklist
    │
    ├─ Check WP-CLI availability
    │
    ├─ Execute with timeout
    │
    ▼
Return result to AI for continuation
```

### Fallback Behavior

If WP-CLI is not available on the server:

```json
{
  "type": "wp_cli_not_available",
  "error": "WP-CLI is not available on this server",
  "instruction": "Please use an alternative method: either provide the code for the user to add manually via the plugin UI, or use PHP API if available."
}
```

The AI then provides manual instructions to the user.

---

## Conversation History Compression

### The Problem

Multi-turn conversations accumulate tokens exponentially:
- Message 1: 3,000 tokens
- Message 2: 4,000 tokens (includes history)
- Message 3: 5,500 tokens
- Message 10: 15,000+ tokens

This increases costs and can hit context limits.

### The Solution

When conversation exceeds ~10 messages, AI can request compression:

1. AI generates summary of conversation so far
2. AI identifies key facts (IDs, current state)
3. Old messages replaced with compressed summary
4. Recent messages (last 4) kept intact

### Implementation

**ChatController.php** - `compress_conversation_history()`:

```php
private function compress_conversation_history(
    array $history,
    string $summary,
    array $key_facts,
    int $preserve_last_messages = 4
): array {
    // Build compressed summary
    $compressed_content = "=== CONVERSATION SUMMARY ===\n";
    $compressed_content .= $summary . "\n\n";

    if (!empty($key_facts)) {
        $compressed_content .= "KEY FACTS:\n";
        foreach ($key_facts as $fact) {
            $compressed_content .= "- {$fact['key']}: {$fact['value']}";
            if ($fact['description']) {
                $compressed_content .= " ({$fact['description']})";
            }
            $compressed_content .= "\n";
        }
    }

    // Create new history: summary + last N messages
    $new_history = [
        ['role' => 'system', 'content' => $compressed_content]
    ];
    $preserved = array_slice($history, -$preserve_last_messages);
    return array_merge($new_history, $preserved);
}
```

### Key Facts Preserved

The AI explicitly saves important data:

```json
{
  "key_facts": [
    { "key": "page_id", "value": 155, "description": "Main landing page" },
    { "key": "elementor_doc_id", "value": 156, "description": "Elementor document" },
    { "key": "current_step", "value": 3, "description": "Adding features section" }
  ]
}
```

This ensures context is never lost, even after compression.

---

## Frontend Roadmap UI

### Components Added

**File**: `assets/js/chat-interface.js`

#### `showRoadmapConfirmation(response)`

Displays the roadmap card with:
- Header (title, step count, estimated time)
- Steps list with status indicators
- "Avvia Roadmap" / "Annulla" buttons

```javascript
showRoadmapConfirmation: function(response) {
    const data = response.data || {};
    const steps = data.steps || [];

    // Build steps HTML with status icons
    let stepsHtml = '';
    steps.forEach(function(step, index) {
        stepsHtml += `
            <div class="creator-roadmap-step" data-step-index="${step.index}">
                <div class="creator-roadmap-step-number">${step.index}</div>
                <div class="creator-roadmap-step-content">
                    <div class="creator-roadmap-step-title">${step.title}</div>
                    <div class="creator-roadmap-step-desc">${step.description}</div>
                </div>
                <div class="creator-roadmap-step-status">
                    <span class="dashicons dashicons-clock"></span>
                </div>
            </div>
        `;
    });

    // Append roadmap card to chat
    // ...
}
```

#### `updateRoadmapStep(stepIndex, status, message)`

Updates visual state of individual steps:

| Status | Visual |
|--------|--------|
| `pending` | Gray, clock icon |
| `executing` | Blue, spinning icon |
| `completed` | Green, checkmark |
| `failed` | Red, X icon |

### CSS Styles

**File**: `assets/css/chat-interface.css`

```css
/* Step states */
.creator-roadmap-step.step-executing {
    background: var(--creator-primary-50);
    border-color: var(--creator-primary-200);
}

.creator-roadmap-step.step-completed {
    background: #ecfdf5;
    border-color: #a7f3d0;
}

.creator-roadmap-step.step-failed {
    background: #fef2f2;
    border-color: #fecaca;
}
```

### Event Handlers

```javascript
// Roadmap confirmation buttons
$(document).on('click', '.creator-confirm-roadmap', this.handleConfirmRoadmap);
$(document).on('click', '.creator-cancel-roadmap', this.handleCancelRoadmap);

handleConfirmRoadmap: function(e) {
    // Hide buttons, show "Esecuzione in corso..."
    $container.find('.creator-roadmap-actions').hide();
    $container.find('.creator-roadmap-status').text('Esecuzione in corso...').show();

    // Send confirmation to continue
    this.sendMessage('Procedi con la roadmap.');
}
```

---

## Files Modified

### Firebase Functions

| File | Changes |
|------|---------|
| `functions/src/services/modelService.ts` | Added micro-step instructions, roadmap/checkpoint/execute_step/wp_cli/compress_history types, plugin integration safety rules |

### WordPress Plugin

| File | Changes |
|------|---------|
| `includes/Chat/ChatController.php` | Added handlers for roadmap, checkpoint, execute_step, wp_cli, compress_history; added `compress_conversation_history()` method |
| `includes/Response/ResponseHandler.php` | Added `handle_roadmap_response()`, `handle_execute_step_response()`, `handle_checkpoint_response()`, `handle_wp_cli_response()`, `handle_compress_history_response()` |
| `includes/Execution/WPCLIExecutor.php` | **NEW FILE** - Safe WP-CLI command executor with whitelist/blacklist |
| `assets/js/chat-interface.js` | Added `showRoadmapConfirmation()`, `updateRoadmapStep()`, roadmap event handlers |
| `assets/css/chat-interface.css` | Added roadmap UI styles, step states, responsive design |

---

## Cost Analysis

### Micro-Step vs Macro-Task Comparison

| Aspect | Macro-task | Micro-step |
|--------|------------|------------|
| **API calls per complex task** | 1-2 | 5-15 |
| **Input tokens per call** | ~3K-5K | Growing (history) |
| **Output tokens per call** | ~3K-8K | ~500-1.5K |
| **Timeout risk** | HIGH | LOW |
| **Reliability** | ~60% | ~95% |

### Example: Landing Page with 3 Sections

**Macro-task approach**:
```
1 call:
- Input: ~3,000 tokens
- Output: ~5,000 tokens
- Cost: ~$0.045
- Risk: HIGH timeout probability
```

**Micro-step approach**:
```
Step 1 (Roadmap): ~$0.017
Step 2 (Create page): ~$0.024
Step 3 (Add hero): ~$0.032
Step 4 (Add features): ~$0.033
Step 5 (Complete): ~$0.033

TOTAL: ~$0.139 (5 calls)
```

### Trade-off

- **Cost increase**: +200-300% for complex tasks
- **Reliability increase**: +35% (60% → 95%)
- **User experience**: Much better (progress visible, no timeouts)

### Mitigation Strategies

1. **History compression** - Reduces token accumulation
2. **Simple tasks use single call** - No overhead for simple operations
3. **Roadmap only for complex tasks** - AI decides based on complexity indicators

---

## Testing

### Test Case: Complex Page Creation

**Request**:
> "Crea una pagina di presentazione di Creator usando Elementor con Hero Section full-width"

**Expected Flow**:

1. AI requests Elementor documentation (`request_docs`)
2. AI creates roadmap with 3-5 steps (`roadmap`)
3. User sees roadmap UI, clicks "Avvia Roadmap"
4. AI executes step 1 (`execute_step`)
5. AI reports checkpoint with page_id (`checkpoint`)
6. UI updates step 1 to "completed" (green checkmark)
7. AI continues with step 2...
8. AI reports completion (`complete`)

### Test Case: Plugin Without API

**Request**:
> "Crea uno snippet su WPCode per nascondere la admin bar"

**Expected Flow**:

1. AI requests WPCode documentation (`request_docs`)
2. Documentation shows no public PHP API
3. AI checks for WP-CLI support
4. If WP-CLI available: uses `wp_cli` type
5. If not available: responds with manual instructions

**Expected Response (no API)**:
```
Ho verificato la documentazione di WPCode. Non espone API pubbliche programmabili.

Ti fornisco il codice da aggiungere manualmente:

1. Vai su Code Snippets > Add New
2. Titolo: "Nascondi Admin Bar"
3. Incolla questo codice:

   add_filter('show_admin_bar', '__return_false');

4. Imposta "Run Everywhere"
5. Salva e attiva lo snippet
```

---

## Summary

The Micro-Step System transforms Creator from a single-shot code generator into a reliable, step-by-step task executor that:

1. **Handles complexity** by breaking tasks into atomic operations
2. **Avoids timeouts** with small, focused code blocks
3. **Tracks progress** visually for better UX
4. **Preserves context** between steps via accumulated_context
5. **Optimizes memory** through conversation compression
6. **Respects plugin boundaries** by using official APIs only
7. **Supports WP-CLI** for plugins that offer it

This makes Creator suitable for production use on managed WordPress hosting with strict timeout limits.
