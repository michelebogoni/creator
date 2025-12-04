# CREATOR - COMPREHENSIVE DEVELOPMENT GUIDE
## Complete Architecture, Implementation Strategy & Milestones

**Version:** 2.0 (Complete Redesign - Dec 2025)  
**Status:** Production Ready with Cleanup Required  
**Last Updated:** 2025-12-03

---

## EXECUTIVE SUMMARY

Creator is an AI-powered WordPress automation plugin that enables users to build, customize, and manage their WordPress sites through natural language conversation. The system uses a **code-based execution model** (not hardcoded actions) that learns plugin capabilities from official documentation and generates executable PHP code in real-time.

**Key Principle:** Creator adapts to YOUR WordPress setup (any plugins, any configuration), not the other way around.

---

## TABLE OF CONTENTS

1. [Vision & Goals](#vision--goals)
2. [Architecture Overview](#architecture-overview)
3. [Current State Analysis](#current-state-analysis)
4. [Cleanup Requirements](#cleanup-requirements)
5. [Missing Pieces](#missing-pieces)
6. [Detailed Technical Architecture](#detailed-technical-architecture)
7. [Implementation Milestones](#implementation-milestones)
8. [Testing & Verification](#testing--verification)
9. [Troubleshooting Guide](#troubleshooting-guide)

---

## VISION & GOALS

### What is Creator?

Creator is a **code-based AI assistant** for WordPress that:

- **Understands your site** (WordPress version, PHP, installed plugins, custom post types, ACF fields, etc.)
- **Learns plugin capabilities** (reads official documentation, extracts available functions)
- **Generates executable code** (PHP code tailored to your specific setup)
- **Executes safely** (security validation, forbidden function checks, rollback capability)
- **Adapts to user skill level** (base/intermediate/advanced â†’ different communication styles)
- **Maintains responsibility** (emphasizes backups, user control, clear consequences)

### Core Principles

1. **Code-Based Execution** 
   - NOT hardcoded actions (old model: "action: create_page")
   - ALWAYS generated PHP code using real plugin functions
   - Adapts dynamically to what's installed on the site

2. **Plugin-Agnostic**
   - Works with ANY WordPress setup
   - Zero mandatory plugin dependencies
   - Suggests plugins when beneficial (not forces)
   - Falls back to vanilla WordPress when plugins missing

3. **Intelligence Through Context**
   - System knows plugin functions via repository (lazy-loaded)
   - AI sees available functions and generates code accordingly
   - No limiting yourself to predefined action types
   - Scalable: new plugins = new capabilities automatically

4. **User Responsibility**
   - Clear about what Creator can do (modify DB, delete content, etc.)
   - Emphasizes daily backups (standard practice)
   - User maintains rollback snapshots (undo button per action)
   - AI iterates if execution fails (up to 3-5 retries)

5. **Transparent Execution**
   - User sees code before execution (confirmation required unless auto_execute=true)
   - Audit trail of all changes
   - Delta snapshots for each action (restore point)
   - Simple undo button if something goes wrong

---

## ARCHITECTURE OVERVIEW

### High-Level Flow

```
USER REQUEST
    
[ChatInterface] - Receives message + context
    
[Context Preparation]
- System info (WordPress version, PHP, plugins)
- Lazy-loaded plugin details (on-demand)
- User profile (base/intermediate/advanced)
- Conversation history (pruned, summarized)
    
[AI Provider Call] (Gemini 3 Pro or Claude Sonnet 4)
- System prompt (universal rules + profile rules + phase rules)
- Conversation history
- Current message
    
[AI Response] (JSON format)
{
  "phase": "discovery|proposal|execution",
  "message": "User-friendly response",
  "code": { /* PHP to execute */ },
  "questions": [ /* If discovery */ ],
  "plan": { /* If proposal */ }
}
    
[Response Parsing]
- Extract JSON from response
- Detect phase (discovery/proposal/execution)
- Load context details if requested (lazy-load)
    
[Execution Phase Check]
if (phase === "execution" && code present):
    
[Code Safety Validation]
- Syntax check (php -l or token_get_all)
- Forbidden function check (exec, shell_exec, etc.)
- Whitelist function check (only WP/plugin functions allowed)
    
[Code Execution]
PRIMARY: WP Code Snippet (traceable, disableable)
FALLBACK: eval() with restrictions OR file write (codice-custom.php)
    
[Delta Snapshot]
- Save before state of affected files/database
- Enable rollback via undo button
    
[Verification]
- ExecutionVerifier checks if intended result happened
- Example: "CPT exists?" "Field registered?" "Post created?"
    
[Result to User]
Success â†’ Show result + [Undo] button
Failure â†’ AI gets error, retries with different approach (up to 5 times)
         If still fails â†’ Suggest manual intervention or contact support
```

### Core Components

| Component | Purpose | Status |
|-----------|---------|--------|
| **ChatInterface.php** | Main orchestrator, message handling, response parsing |  Production |
| **CodeExecutor.php** | Execute PHP code safely (WP Code or eval) |  Production |
| **ContextLoader.php** | Load plugin details on-demand (lazy-load) |  Production |
| **ExecutionVerifier.php** | Verify action results (did CPT get created?) |  Production |
| **PhaseDetector.php** | Classify response phase (discovery/proposal/execution) |  Production |
| **SystemPrompts.php** | AI behavior instructions (3 phases Ã— 3 levels) |  Production |
| **SnapshotManager.php** | Save/restore deltas for rollback |  Production |
| **PluginDocsRepository** (Firebase) | Store plugin function docs (lazy-loaded) |  Production |
| **ActionExecutor.php** | LEGACY - Action type mapping (REMOVE) | âŒ Deprecated |

---

## CURRENT STATE ANALYSIS

### What's Working 

```
 JSON Response Format
   - AI responds in clean JSON with required fields
   - ChatInterface.php:867-924 parses correctly
   - extract_json_from_response() handles markdown code blocks

 Phase Detection
   - PhaseDetector classifies responses (discovery/proposal/execution)
   - Fallback to discovery if unclear
   - Routing correct to appropriate handler

 Code Execution Pipeline
   - CodeExecutor.php handles dual execution:
     * Primary: WP Code snippet (traceable)
     * Fallback: eval() with security checks
   - Forbidden function list implemented
   - Whitelist of WP/plugin functions implemented

 Lazy-Load Context System
   - ContextLoader.php provides on-demand plugin details
   - AI requests via context_request actions
   - Loaded details injected into prompt (## Loaded Details section)
   - Reduces initial context size dramatically

 Conversation Management
   - History pruning (keeps last 10 complete messages)
   - Summarization of older messages (2-3 lines)
   - Token budget management (~2.7k tokens typical)
   - Safe for both Gemini (1M tokens) and Claude (200k tokens)

 File Attachment Support
   - WordPress: Validation, base64 encoding, size limits (10MB, 3 files)
   - Firebase: File extraction and routing
   - Provider formatting: Gemini (inline_data), Claude (image.source)
   - AI receives files in native provider format

 User Profile Levels
   - Base (Principiante): Simple language, detailed explanations
   - Intermediate: Balanced approach
   - Advanced: Complex solutions, technical details
   - SystemPrompts.php has profile-specific rules

 Backup & Rollback
   - SnapshotManager creates delta snapshots before execution
   - Undo button appears next to actions in chat
   - Button disabled if snapshot older than retention (auto-cleanup)
```

### What Needs Attention âš ï¸

```
âš ï¸ LEGACY CODE STILL PRESENT
   - ActionExecutor.php exists (1294 lines) but unused
   - normalize_action_type() exists but shouldn't
   - "actions" array marked DEPRECATO in SystemPrompts but never removed
   - Status: CRUFT - needs cleanup

âš ï¸ WP CODE FALLBACK STRATEGY UNDEFINED
   - If WP Code snippet not installed:
     * Currently: Falls back to eval()
     * Should define: Write to codice-custom.php (or similar)
   - Need clear strategy for each code type:
     * PHP â†’ functions.php or codice-custom.php?
     * CSS â†’ custom-styles.css or wp-admin/custom.css?
     * JavaScript â†’ codice-custom.js?
   - File naming convention: codice-custom-{type}.{ext} ?

âš ï¸ AI FILE ATTACHMENT AWARENESS UNCLEAR
   - Files passed to AI correctly (base64)
   - But: Does AI know it received files? Is system prompt explicit?
   - Need explicit instruction in system prompt:
     "If user attached files, they are available in this message.
      Use them to inform your analysis."

âš ï¸ ROLLBACK UX NOT FULLY DEFINED
   - Undo button exists in chat
   - But: What if snapshot is stale/deleted?
   - What if rollback fails?
   - Flow: User clicks Undo â†’ What happens? How long does it take?
   - Need UI feedback (loading, success, error states)

âš ï¸ ERROR RECOVERY NOT DOCUMENTED
   - If AI executes code and verification fails:
     * Current: AI retries (good)
     * But: After 5 retries, what message?
     * Suggest rollback? Manual intervention?
     * Contact support?
   - Need clear error recovery strategy
```

---

## CLEANUP REQUIREMENTS

### PHASE 1: Remove Legacy Action System (CRITICAL)

**Files to Clean:**

1. **ActionExecutor.php** - REMOVE ENTIRELY
   - No longer used (code-based execution is primary)
   - 1294 lines of dead code
   - Removal: Delete file completely

2. **SystemPrompts.php** - REMOVE Action References
   - Remove "actions" array definition
   - Remove section: "Tipi di Azione Supportati"
   - Remove any mention of action types (create_page, create_post, etc.)
   - Verify: Only code-based execution mentioned

3. **normalize_action_type() function** - REMOVE
   - Wherever it exists, delete
   - If called anywhere, remove those calls

4. **ChatInterface.php** - Remove Action Handling
   - If `$this->action_executor` instantiation exists â†’ remove
   - If action parsing exists â†’ remove
   - Verify: Only code execution path remains

5. **System Prompts - REMOVE**
   - "DEPRECATED: actions array" comment â†’ remove
   - "If you want to use action types, map them via..." â†’ remove
   - Any backward compatibility mention â†’ remove

**Verification After Cleanup:**
```bash
grep -r "ActionExecutor" wp-content/plugins/creator/
grep -r "normalize_action_type" wp-content/plugins/creator/
grep -r "\"actions\":" wp-content/plugins/creator/
# All should return NOTHING
```

### PHASE 2: Define WP Code Fallback Strategy (CRITICAL)

**Current Status:** Undefined behavior if WP Code not installed

**Strategy to Implement:**

```
IF WP Code snippet available:
   Use it (traceable, disableable, best practice)

ELSE IF WP Code not available:
   For PHP code:
     Write to: wp-content/plugins/creator/codice-custom.php
       (append to file, timestamp each section)
  
   For CSS code:
     Write to: wp-content/plugins/creator/codice-custom.css
       (append to file, timestamp each section)
  
   For JavaScript:
     Write to: wp-content/plugins/creator/codice-custom.js
       (append to file, timestamp each section)
  
   For functions.php modifications:
      Create backup of functions.php first
      Append code with clear markers:
        // === CREATOR MODIFICATION [timestamp] ===
        [code]
        // === END CREATOR MODIFICATION ===

File Structure Created:
wp-content/plugins/creator/
 codice-custom.php      (PHP functions, hooks)
 codice-custom.css      (CSS rules)
 codice-custom.js       (JavaScript)
 codice-manifest.json   (registry of all modifications)
  {
    "modifications": [
      {
        "id": "action_123",
        "type": "php|css|js",
        "file": "codice-custom.php",
        "start_line": 45,
        "end_line": 67,
        "timestamp": "2025-12-03T19:30:00Z",
        "description": "Created CPT 'Projects'",
        "delta_snapshot": { /* before/after */ }
      }
    ]
  }
```

**Implementation in CodeExecutor.php:**

```php
// Pseudo-code for fallback strategy
private function execute_directly( $php_code ) {
    if ( class_exists( 'WPCode_Lite' ) ) {
        return $this->execute_via_wpcode( $php_code );
    }
    
    // WP Code not available - use custom files
    $code_type = $this->detect_code_type( $php_code );
    
    switch ( $code_type ) {
        case 'php':
            return $this->write_to_custom_file( 'codice-custom.php', $php_code );
        case 'css':
            return $this->write_to_custom_file( 'codice-custom.css', $php_code );
        case 'js':
            return $this->write_to_custom_file( 'codice-custom.js', $php_code );
        default:
            // Last resort: eval() with security
            return $this->eval_with_restrictions( $php_code );
    }
}

private function write_to_custom_file( $file, $code ) {
    $file_path = WP_CONTENT_DIR . "/plugins/creator/{$file}";
    
    // Create backup
    $this->delta_manager->snapshot_before( $file_path );
    
    // Append with markers
    $marked_code = sprintf(
        "\n\n// === CREATOR MODIFICATION %s ===\n%s\n// === END CREATOR MODIFICATION ===",
        current_time( 'mysql' ),
        $code
    );
    
    file_put_contents( $file_path, $marked_code, FILE_APPEND );
    
    // Register in manifest
    $this->register_modification( [
        'file' => $file,
        'code' => $code,
        'type' => $this->get_extension_type( $file )
    ] );
    
    return [ 'success' => true, 'location' => $file_path ];
}
```

---

## MISSING PIECES

### 1. System Prompt - File Attachment Awareness

**Current State:** Files passed to AI but system prompt doesn't mention them

**Add to SystemPrompts.php - Universal Rules Section:**

```
### FILE ATTACHMENTS

When the user attaches files (images, PDFs, documents, code):
- The files are available in this message
- Analyze them to inform your response
- Use attached images to understand design intent
- Use attached documents to understand requirements
- Use attached code to debug or improve

Examples:
- User attaches screenshot of error â†’ Read error, provide fix
- User attaches design mockup â†’ Understand layout intent, match in code
- User attaches requirements PDF â†’ Use to generate accurate solution

IMPORTANT: Always reference the attached files in your response when relevant.
Example: "Based on the screenshot you shared, the error is..."
```

### 2. Rollback UX & Triggering

**Current State:** Undo button exists but flow unclear

**Add to ChatInterface.php - Message Handling:**

```php
// When action executed, add undo button to message
$message_output = [
    'content' => $ai_response,
    'execution' => $execution_result,
    'undo_available' => true,
    'undo_button' => [
        'label' => 'Undo this action',
        'action_id' => $action_id,
        'snapshot_id' => $snapshot_id,
        'expires_in_seconds' => 86400 * 7 // 7 days
    ]
];

// When user clicks Undo:
public function handle_undo( $action_id, $snapshot_id ) {
    // 1. Check snapshot exists and is fresh
    if ( ! $this->snapshot_manager->exists( $snapshot_id ) ) {
        return [
            'success' => false,
            'message' => 'Rollback snapshot expired. Please use backup system.',
            'suggestion' => 'Restore from daily backup'
        ];
    }
    
    // 2. Restore from snapshot
    $restore_result = $this->snapshot_manager->restore( $snapshot_id );
    
    if ( $restore_result['success'] ) {
        return [
            'success' => true,
            'message' => 'Action rolled back successfully',
            'previous_state' => $restore_result['restored_data']
        ];
    } else {
        // Restoration failed - suggest manual intervention
        return [
            'success' => false,
            'message' => 'Rollback failed (complex modification)',
            'suggestion' => 'Please restore from daily backup',
            'error' => $restore_result['error'],
            'recommendation' => 'Contact support if needed'
        ];
    }
}
```

### 3. Error Recovery Strategy

**Add to CodeExecutor.php - Retry Logic:**

```php
private function execute_with_retry( $code, $max_retries = 5 ) {
    $attempts = 0;
    $last_error = null;
    
    while ( $attempts < $max_retries ) {
        $attempts++;
        
        // Execute code
        $result = $this->execute_code( $code );
        
        if ( $result['success'] ) {
            return $result; // Success
        }
        
        $last_error = $result['error'];
        
        if ( $attempts < $max_retries ) {
            // Send error back to AI for retry
            $retry_result = $this->ai_retry_with_error( $code, $last_error );
            
            if ( $retry_result['new_code'] ) {
                $code = $retry_result['new_code']; // Try new approach
            } else {
                break; // AI couldn't suggest fix
            }
        }
    }
    
    // Max retries exhausted
    return [
        'success' => false,
        'error' => $last_error,
        'message' => 'Action failed after 5 attempts',
        'next_steps' => [
            'suggestion' => 'Manual verification or support contact',
            'rollback' => 'Click Undo to reverse any partial changes',
            'logs' => 'Check error logs for details'
        ]
    ];
}
```

### 4. Code Type Detection

**Add to CodeExecutor.php:**

```php
private function detect_code_type( $code ) {
    // Detect if code is PHP, CSS, JS, HTML, etc.
    
    if ( preg_match( '/^<style|\.css|^\..*{|color:|background:/', $code ) ) {
        return 'css';
    }
    
    if ( preg_match( '/^<script|\.js|function|const |let |var |=>|document\.|jQuery/', $code ) ) {
        return 'js';
    }
    
    if ( preg_match( '/^<|DOCTYPE|html|head|body/', $code ) ) {
        return 'html';
    }
    
    // Default to PHP
    return 'php';
}
```

---

## DETAILED TECHNICAL ARCHITECTURE

### System Prompts Structure (SystemPrompts.php)

**Class Methods:**

```php
class SystemPrompts {
    
    // Entry Points (Public)
    public function get_universal_rules(): string
    public function get_profile_prompt( string $level ): string
    public function get_discovery_rules( string $level ): string
    public function get_proposal_rules( string $level ): string
    public function get_execution_rules( string $level ): string
    
    // Profile Rules (Private)
    private function get_base_profile_prompt(): string
    private function get_intermediate_profile_prompt(): string
    private function get_advanced_profile_prompt(): string
    
    // Discovery Phase (Private)
    private function get_base_discovery_additions(): string
    private function get_intermediate_discovery_additions(): string
    private function get_advanced_discovery_additions(): string
    
    // Proposal Phase (Private)
    private function get_base_proposal_additions(): string
    private function get_intermediate_proposal_additions(): string
    private function get_advanced_proposal_additions(): string
    
    // Execution Phase (Private)
    private function get_base_execution_additions(): string
    private function get_intermediate_execution_additions(): string
    private function get_advanced_execution_additions(): string
}
```

**Composition Pattern:**

Each public `get_*_rules()` method:
1. Starts with universal/base rules (same for all levels)
2. Appends level-specific additions
3. Returns combined prompt

Example:
```php
public function get_execution_rules( string $level ): string {
    $base = <<<'RULES'
## EXECUTION PHASE
...base execution rules...
RULES;
    
    return $base . "\n\n" . match($level) {
        'base' => $this->get_base_execution_additions(),
        'advanced' => $this->get_advanced_execution_additions(),
        default => $this->get_intermediate_execution_additions(),
    };
}
```

### Response JSON Schema

**AI Must Return (Code Execution Example):**

```json
{
  "phase": "execution",
  "message": "Ecco il codice per creare il CPT 'Progetti'...",
  "confidence": 0.95,
  "intent": "create_custom_post_type_with_taxonomy",
  
  "code": {
    "type": "wpcode_snippet",
    "title": "Register CPT 'Progetti' with Hierarchical Taxonomy",
    "description": "Registers a custom post type for projects with a hierarchical taxonomy for categories",
    "language": "php",
    "content": "<?php\nregister_post_type('progetti', [\n    'label' => 'Progetti',\n    'public' => true,\n    'has_archive' => true,\n    'supports' => ['title', 'editor', 'thumbnail'],\n]);\n\nregister_taxonomy('project_category', 'progetti', [\n    'label' => 'Project Categories',\n    'hierarchical' => true,\n]);\n?>",
    "location": "everywhere",
    "auto_execute": false
  },
  
  "questions": [],
  "plan": null,
  "actions": []
}
```

### Code Execution Pipeline

**Flow in CodeExecutor.php:**

```
1. RECEIVE CODE REQUEST
    Check syntax: php -l or token_get_all()
    If syntax error: Return error, don't execute
    Continue

2. SECURITY VALIDATION
    Scan for forbidden functions (exec, shell_exec, etc.)
    Check whitelist (wp_insert_post, acf_add_field_group, etc.)
    If forbidden found: Return error, don't execute
    Continue

3. CREATE DELTA SNAPSHOT
    Detect affected files/DB tables
    Take "before" snapshot
    Store snapshot ID for rollback

4. EXECUTE CODE
    PRIMARY: Try WP Code Snippet
      Create snippet in database
      Snippet marked: auto_execute = true
      WP loads and runs it
   
    FALLBACK (if WP Code unavailable):
      Detect code type (PHP/CSS/JS)
      Write to appropriate file (codice-custom.*)
      eval() not preferred, but available as last resort
   
    Capture output + errors

5. VERIFICATION
    Run ExecutionVerifier checks
    Example: "Does CPT 'progetti' exist?"
    If verification passes: Success
    If verification fails: Prepare for retry

6. RETURN RESULT
    success: true/false
    message: User-friendly description
    snapshot_id: For rollback button
    verification: What was checked
```

### Context Loading (Lazy-Load)

**When AI Requests Details:**

```
AI Response includes:
{
  "context_request": {
    "type": "get_plugin_details",
    "params": { "slug": "elementor" }
  }
}

OR

{
  "context_request": {
    "type": "get_acf_details",
    "params": { "group": "Hero Section" }
  }
}

ChatInterface receives context_request:
1. Call ContextLoader->handle_context_request()
2. Load data (plugin functions, ACF fields, etc.)
3. Verify it's available in repository
    If cached: Return immediately
    If not cached: AI research + save to repository
4. Store result in message metadata['context_data']
5. Next turn: inject into prompt as "## Loaded Details"
```

### Conversation History Management

**Pruning Strategy in ChatInterface.php:**

```php
private function build_conversation_history() {
    $all_messages = $this->get_chat_messages();
    
    // Keep last 10 complete messages
    if ( count( $all_messages ) > 10 ) {
        // Summarize old messages (2-3 lines max)
        $old_messages = array_slice( $all_messages, 0, -10 );
        $summary = $this->ai_summarize( $old_messages );
        
        $history = [
            [
                'role' => 'system',
                'content' => "Previous conversation summary:\n{$summary}"
            ],
            // + last 10 messages
            ...array_slice( $all_messages, -10 )
        ];
    } else {
        $history = $all_messages;
    }
    
    return $history;
}
```

---

## IMPLEMENTATION MILESTONES

### MILESTONE 1: Clean Up Legacy Code (1-2 days)

**Objective:** Remove ActionExecutor and action type system entirely

**Tasks:**
- [ ] Delete ActionExecutor.php
- [ ] Remove normalize_action_type() function
- [ ] Remove "actions" array from SystemPrompts
- [ ] Remove action-related comments from ChatInterface.php
- [ ] Remove action parsing code
- [ ] Verify no references to "action types" remain
- [ ] Commit: "refactor: Remove legacy ActionExecutor"
- [ ] Test: Verify chat still works normally

**Success Criteria:**
- [ ] No grep results for "ActionExecutor"
- [ ] No grep results for "normalize_action_type"
- [ ] No grep results for "actions: \[" in responses
- [ ] Chat functionality unchanged

---

### MILESTONE 2: Define & Implement WP Code Fallback (2-3 days)

**Objective:** Clear strategy for code execution when WP Code not installed

**Tasks:**
- [ ] Document fallback strategy (codice-custom-*.php files)
- [ ] Implement file write methods in CodeExecutor
- [ ] Create codice-manifest.json registry system
- [ ] Implement modification tracking
- [ ] Add code type detection (PHP/CSS/JS)
- [ ] Test fallback paths:
  - [ ] Write PHP to codice-custom.php
  - [ ] Write CSS to codice-custom.css
  - [ ] Write JS to codice-custom.js
- [ ] Test rollback from custom files
- [ ] Commit: "feat: Implement WP Code fallback strategy"

**Success Criteria:**
- [ ] Custom files created correctly when WP Code unavailable
- [ ] Code executes from custom files
- [ ] Manifest registry tracks modifications
- [ ] Rollback works for custom file modifications

---

### MILESTONE 3: Add File Attachment System Prompts (1 day)

**Objective:** AI understands and uses file attachments

**Tasks:**
- [ ] Add "FILE ATTACHMENTS" section to SystemPrompts.php
- [ ] Instruct AI to analyze attached files
- [ ] Add examples of file usage
- [ ] Update proposal phase to mention attached files if present
- [ ] Test: Attach image â†’ AI analyzes and references it
- [ ] Test: Attach PDF â†’ AI reads content and uses it
- [ ] Commit: "feat: Add file attachment system prompts"

**Success Criteria:**
- [ ] AI acknowledges attached files in response
- [ ] AI uses file content in its analysis
- [ ] AI references files when relevant

---

### MILESTONE 4: Implement Rollback UX & Error Recovery (2-3 days)

**Objective:** Complete rollback flow and error recovery strategy

**Tasks:**
- [ ] Implement handle_undo() in ChatInterface
- [ ] Add undo button to executed action messages
- [ ] Disable button if snapshot expired
- [ ] Handle snapshot not found gracefully
- [ ] Implement AI retry logic (up to 5 attempts)
- [ ] Add error recovery suggestions
- [ ] Test rollback scenarios:
  - [ ] Fresh snapshot (success)
  - [ ] Old snapshot (expired)
  - [ ] Snapshot corrupted (error message)
  - [ ] Partial rollback (partial success)
- [ ] Commit: "feat: Implement rollback UX and error recovery"

**Success Criteria:**
- [ ] Undo button appears after action
- [ ] Undo button works for fresh snapshots
- [ ] Undo button disabled for old snapshots
- [ ] Error messages are helpful
- [ ] AI retries on failure

---

### MILESTONE 5: System Prompts Documentation (1 day)

**Objective:** Document exact content of all system prompts

**Tasks:**
- [ ] Export all get_universal_rules() content â†’ document
- [ ] Export all get_*_profile_prompt() â†’ document per level
- [ ] Export all get_*_discovery_rules() â†’ document per level
- [ ] Export all get_*_proposal_rules() â†’ document per level
- [ ] Export all get_*_execution_rules() â†’ document per level
- [ ] Create SYSTEM_PROMPTS.md with exact content
- [ ] Document how prompts are composed
- [ ] Document token budget impact per prompt size
- [ ] Commit: "docs: Document complete system prompts"

**Success Criteria:**
- [ ] SYSTEM_PROMPTS.md is comprehensive
- [ ] Exact system prompts visible for audit/debug
- [ ] Token usage documented

---

### MILESTONE 6: End-to-End Testing (2-3 days)

**Objective:** Verify all paths work correctly

**Tasks:**
- [ ] Test Suite Setup:
  - [ ] Test Discovery Phase (AI asks questions, user responds)
  - [ ] Test Proposal Phase (AI proposes plan)
  - [ ] Test Execution Phase (AI generates code, user confirms)
  - [ ] Test Code Execution:
    - [ ] WP Code path
    - [ ] Fallback path (custom files)
  - [ ] Test Verification:
    - [ ] CPT creation verified
    - [ ] ACF field creation verified
    - [ ] Post creation verified
  - [ ] Test Rollback:
    - [ ] Undo fresh action
    - [ ] Undo old action (expired snapshot)
    - [ ] Partial rollback
  - [ ] Test Error Scenarios:
    - [ ] Syntax error in code
    - [ ] Forbidden function in code
    - [ ] Execution fails â†’ AI retries
    - [ ] Max retries exceeded
  - [ ] Test File Attachments:
    - [ ] AI receives image
    - [ ] AI receives PDF
    - [ ] AI analyzes and uses files
  - [ ] Test Lazy-Load:
    - [ ] AI requests plugin details
    - [ ] Details loaded from repository
    - [ ] AI uses details in code
  - [ ] Test Conversation History:
    - [ ] Long conversation (20+ messages)
    - [ ] History pruned correctly
    - [ ] Context maintained

- [ ] Create test scenarios document
- [ ] Document pass/fail for each scenario
- [ ] Fix any failures
- [ ] Commit: "test: Add comprehensive end-to-end test suite"

**Success Criteria:**
- [ ] All scenarios pass
- [ ] Error handling works
- [ ] Rollback tested
- [ ] Performance acceptable

---

### MILESTONE 7: Production Readiness (1 day)

**Objective:** Final cleanup and deployment preparation

**Tasks:**
- [ ] Code review of all changes
- [ ] Security audit:
  - [ ] Forbidden function list comprehensive
  - [ ] Whitelist restrictive enough
  - [ ] No eval() without guards
  - [ ] File write operations safe
- [ ] Performance check:
  - [ ] Initial context < 3k tokens
  - [ ] Lazy-load < 1 second
  - [ ] Code execution < 5 seconds
- [ ] Documentation:
  - [ ] README updated
  - [ ] Architecture documented
  - [ ] System prompts documented
  - [ ] Testing documented
- [ ] Final deploy:
  - [ ] Merge to main
  - [ ] Firebase deploy
  - [ ] Production test
  - [ ] Monitoring setup

**Success Criteria:**
- [ ] All security checks pass
- [ ] Performance acceptable
- [ ] Documentation complete
- [ ] Ready for production release

---

## TESTING & VERIFICATION

### Test Scenarios

#### Scenario 1: Create CPT with ACF (Discovery â†’ Proposal â†’ Execution)

```
USER: "Crea un CPT 'Projects' con ACF per data inizio e budget"

EXPECTED:
  Phase 1 (DISCOVERY): AI chiede:
    - "Deve essere gerarchico?"
    - "Che slug vuoi?"
    - "Chi deve vederlo (pubblico/privato)?"
  
  Phase 2 (PROPOSAL): AI propone:
    - Piano step-by-step
    - Stima complessitÃ 
    - Stima crediti
  
  Phase 3 (EXECUTION): AI genera:
    - PHP code con register_post_type()
    - PHP code con acf_add_field_group()
  
  Execution:
    - Code eseguito via WP Code o codice-custom.php
    - Verifica: CPT esiste? ACF fields registrati?
    - Snapshot salvato per rollback

VERIFY:
  â˜ All 3 phases execute
  â˜ Code is executable PHP
  â˜ No hardcoded actions used
  â˜ Undo button available
```

#### Scenario 2: Attach Design Mockup â†’ Generate Landing Page

```
USER: Allega immagine di mockup
      "Crea landing page che segue questo design usando Elementor"

EXPECTED:
  Phase 1 (DISCOVERY): AI chiede:
    - "Qual Ã¨ il CTA principale?"
    - "Quante sezioni?"
  
  Phase 2 (PROPOSAL): AI propone:
    - Piano basato su mockup
    - Elementor widgets da usare
  
  Phase 3 (EXECUTION): AI genera:
    - PHP code per creare pagina
    - JavaScript/JSON per Elementor data
    - Layout e stili da mockup

VERIFY:
  â˜ AI reads and analyzes attached image
  â˜ AI references design in responses
  â˜ AI generates code matching design
  â˜ Page created correctly
```

#### Scenario 3: Error & Recovery

```
USER: "Aggiungi campo obbligatorio al CPT projects"

EXECUTION FAILS (e.g., CPT doesn't exist)

EXPECTED:
  1. CodeExecutor detects error
  2. AI receives error: "CPT 'projects' non trovato"
  3. AI RETRY 1: Genera codice diverso
  4. Se ancora fallisce â†’ RETRY 2-5
  5. Dopo 5 tentativi: Mostra messaggio "Contatta supporto"

VERIFY:
  â˜ Error detected
  â˜ AI retries with different approach
  â˜ Max 5 retries enforced
  â˜ User gets helpful error message
```

#### Scenario 4: Rollback

```
USER: Esegue azione
      Clicks "Undo"

EXPECTED:
  Fresh snapshot (< 7 days):
    - Rollback executes immediately
    - State before action restored
    - Success message
  
  Old snapshot (> 7 days):
    - Undo button disabled
    - Message: "Snapshot expired, use backup system"

VERIFY:
  â˜ Fresh undo works
  â˜ State properly restored
  â˜ Old undo handled gracefully
```

#### Scenario 5: Lazy-Load Context

```
USER: "Crea integrazioni con RankMath per SEO"

EXPECTED:
  AI response includes:
    "Voglio leggere le funzioni RankMath disponibili"
  
  System recognizes context_request:
    - Checks repository (cached?)
    - If not cached: AI research + save
    - Load details: get_rank_math_details()
  
  Next turn: AI vede:
    "## Loaded Details (from previous request)
     ### get_rank_math_details:
     {function_list, docs_urls, ...}"
  
  AI genera codice usando funzioni RankMath

VERIFY:
  â˜ Context request recognized
  â˜ Details loaded from repository
  â˜ AI uses loaded details
  â˜ Code uses correct functions
```

---

## TROUBLESHOOTING GUIDE

### Issue: "Unknown action type: create_elementor_page"

**Cause:** Still using old ActionExecutor model

**Solution:**
1. Verify ActionExecutor.php removed
2. Verify SystemPrompts has no action types listed
3. Check ChatInterface.php uses CodeExecutor only
4. Verify AI response is JSON with `code` field (not `actions`)

**Fix:**
```bash
# Remove legacy code
rm wp-content/plugins/creator/includes/ActionExecutor.php

# Verify
grep -r "ActionExecutor" wp-content/plugins/creator/
# Should return NOTHING
```

---

### Issue: Code doesn't execute, returns error

**Possible Causes:**

1. **WP Code not installed + fallback undefined**
   - Solution: Verify fallback strategy implemented (Milestone 2)

2. **Forbidden function in code**
   - Solution: Check CodeExecutor::FORBIDDEN_FUNCTIONS list
   - Fix: Remove offending function or add to whitelist if safe

3. **Syntax error in PHP**
   - Solution: Check syntax validation (php -l)
   - Fix: Ask AI to regenerate with corrected syntax

4. **File write permissions**
   - Solution: Verify wp-content/plugins/creator is writable
   - Fix: chmod 755 (or appropriate permissions)

---

### Issue: File attachments ignored by AI

**Cause:** System prompt doesn't instruct AI about files

**Solution:** Verify Milestone 3 complete (file attachment system prompts)

**Fix:**
```php
// In SystemPrompts.php, verify this section exists:
// ### FILE ATTACHMENTS
// When the user attaches files...
```

---

### Issue: Rollback doesn't work

**Possible Causes:**

1. **Snapshot expired**
   - Solution: Check SnapshotManager retention policy
   - Fix: Extend retention if needed, or use backup

2. **Snapshot corrupted**
   - Solution: Check snapshot file integrity
   - Fix: Recommend backup restore

3. **Rollback code fails**
   - Solution: Complex state changes might not be rollback-able
   - Fix: Manual intervention or backup restore

---

### Issue: Token budget exceeded

**Cause:** Context too large or long conversation

**Solution:**
1. Verify conversation pruning works (keep 10 messages)
2. Verify context lazy-load reduces initial size
3. Check system prompts for unnecessary verbosity

**Debug:**
```php
// In ChatInterface.php, log token usage
$token_count = strlen( $prompt ) / 4; // Rough estimate
if ( $token_count > 20000 ) {
    error_log( "WARNING: Token budget high: {$token_count}" );
}
```

---

## NEXT STEPS

### Immediate (This Week)

1. **Execute Milestone 1:** Remove ActionExecutor
2. **Execute Milestone 2:** Implement WP Code fallback
3. **Test:** Verify chat still works

### Short Term (Next Week)

4. **Execute Milestone 3:** File attachment prompts
5. **Execute Milestone 4:** Rollback UX
6. **Execute Milestone 5:** System prompts documentation

### Medium Term (End of Month)

7. **Execute Milestone 6:** Comprehensive testing
8. **Execute Milestone 7:** Production readiness
9. **Deploy** to production

---

## CONTACT & SUPPORT

For questions or clarifications about this architecture:

- **Architecture questions:** Review this document
- **Implementation questions:** Check code comments
- **Bug reports:** Include error logs + scenario reproduction
- **Feature requests:** Document use case + expected behavior

---

**Document Version:** 2.0  
**Last Updated:** 2025-12-03  
**Status:** Ready for Implementation
