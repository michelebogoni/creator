# Creator Core Plugin - MILESTONE 1
## WordPress AI-Powered Development Assistant Plugin

**Versione:** 2.2 (MILESTONE 1 - Deliverability-Focused)  
**Stack:** WordPress + PHP 7.4+ + Elementor + WP Code + ACF + Rank Math + WooCommerce + LiteSpeed Cache  
**Audience:** Claude Code, Gemini, Human Developers  
**Status:** Ready for Implementation

---

## 🎯 Visione Complessiva del Progetto

### Cosa è Creator?

**Creator** è un **sistema AI-powered per WordPress** che permette agli amministratori di:
- ✅ Creare pagine, post, custom fields via conversazione naturale
- ✅ Gestire integrazioni (Elementor, ACF, Rank Math, WooCommerce)
- ✅ Eseguire operazioni WordPress complesse in modo sicuro e reversibile
- ✅ Mantenere traccia completa di tutte le azioni (audit trail)
- ✅ Annullare qualsiasi operazione in qualsiasi momento (delta snapshots)

### Architettura Complessiva

```
┌─────────────────────────────────────────────────────┐
│  WordPress Site (micheleb174.sg-host.com)           │
│                                                      │
│  ┌────────────────────────────────────────────────┐ │
│  │  Creator Core Plugin (FASE 2 - YOU ARE HERE)   │ │
│  │  ✅ Dashboard, Chat, Backup, Permissions      │ │
│  │  ✅ Action Executor, Integrations             │ │
│  │  ✅ Audit Logging, Delta Snapshots            │ │
│  └────────────────────────────────────────────────┘ │
│                    ↓ (HTTPS API)                     │
└─────────────────────────────────────────────────────┘
                      ↓
         ┌─────────────────────────────┐
         │  Firebase Cloud Functions    │
         │  (FASE 1 - COMPLETED ✅)     │
         │                             │
         │ ✅ License Management       │
         │ ✅ User Authentication      │
         │ ✅ AI Provider Routing      │
         │ ✅ Cost Tracking            │
         │ ✅ Async Task Processing    │
         │ ✅ Logging & Audit          │
         └─────────────────────────────┘
                      ↓ (HTTPS API)
        ┌──────────────────────────────────┐
        │  AI Providers (Routed by Proxy)  │
        ├──────────────────────────────────┤
        │ • OpenAI (GPT-4, o1)             │
        │ • Google Gemini                  │
        │ • Anthropic Claude               │
        └──────────────────────────────────┘
```

### Cosa è stato completato (FASE 1)

**Firebase Proxy** ✅ COMPLETO:
- ✅ Authentication & License Management
- ✅ AI Provider Routing (OpenAI, Gemini, Claude)
- ✅ Cost Tracking & Usage Analytics
- ✅ Async Task Processing
- ✅ Complete Audit Logging
- ✅ Multi-provider Load Balancing

**Repository:** `https://github.com/michelebogoni/creator` → `/functions/`

### Cosa stai costruendo (FASE 2 - MILESTONE 1)

**Creator Core WordPress Plugin** 🚀 IN PROGRESS:
- 🔨 Core Infrastructure (Dashboard, Chat Interface, Settings)
- 🔨 Database Schema (6 tables per chat/action/backup management)
- 🔨 Backup & Snapshot System (Delta backups with rollback)
- 🔨 Permission System (Capability checking per role)
- 🔨 Audit Logging (Every operation tracked)
- 🔨 Integration Detection (Elementor, ACF, Rank Math, etc.)
- 🔨 Action Executor Foundation (Ready for MILESTONE 2)

---

## 🚀 Environment & Credentials

### Target WordPress Installation

```
URL: https://micheleb174.sg-host.com
Admin Dashboard: https://micheleb174.sg-host.com/wp-admin
Admin Email: hello@aloudmarketing.com
Admin Password: 32)13v5-_o#@

Database: WordPress default (wp_*)
Theme: Active (detect on first run)
Plugins Required: Elementor, WP Code
Plugins Optional: ACF, Rank Math, WooCommerce, LiteSpeed Cache
```

### Firebase Proxy Connection

The plugin communicates with Firebase Proxy via:

```
API Endpoint: https://creator-ai-proxy.firebaseapp.com/api/
Authentication: License Key + Site Token (stored in wp_options)

API Keys to use (from FASE 1):
- GEMINI_KEY: nascosta
- OPENAI_KEY: nascosta
- CLAUDE_KEY: nascosta
```

These are stored in Firebase Proxy, not in the WordPress plugin.

### Repository Setup

```
GitHub Repository: https://github.com/michelebogoni/creator-core-plugin
Branch: main (contains CLAUDE.md + ready for implementation)
Working Branch: feature/creator-core-milestone-1 (where Claude Code works)
```

---

## 🏗️ MILESTONE 1: Core Infrastructure & Admin Dashboard

### Obiettivo

Creare la base del plugin che permetterà:
- Dashboard amministrativo funzionante
- Chat interface pronta per l'AI
- Sistema di backup completamente reversibile
- Gestione permessi per ruoli WordPress
- Tracking completo di tutte le azioni

### Deliverables (12 componenti)

#### 1.1 Plugin Scaffolding

Directory structure:
```
creator-core/
├── creator-core.php                 # Main plugin file
├── assets/
│   ├── css/
│   │   ├── admin-dashboard.css
│   │   ├── chat-interface.css
│   │   └── setup-wizard.css
│   └── js/
│       ├── admin-dashboard.js
│       ├── chat-interface.js
│       ├── action-handler.js
│       └── setup-wizard.js
├── includes/
│   ├── Admin/
│   │   ├── Dashboard.php
│   │   ├── Settings.php
│   │   └── SetupWizard.php
│   ├── Chat/
│   │   ├── ChatInterface.php
│   │   ├── MessageHandler.php
│   │   └── ContextCollector.php
│   ├── Backup/
│   │   ├── SnapshotManager.php
│   │   ├── DeltaBackup.php
│   │   └── Rollback.php
│   ├── Permission/
│   │   ├── CapabilityChecker.php
│   │   └── RoleMapper.php
│   ├── Audit/
│   │   ├── AuditLogger.php
│   │   └── OperationTracker.php
│   ├── Integrations/
│   │   ├── ProxyClient.php
│   │   ├── PluginDetector.php
│   │   ├── ElementorIntegration.php
│   │   ├── ACFIntegration.php
│   │   ├── RankMathIntegration.php
│   │   ├── WooCommerceIntegration.php
│   │   ├── WPCodeIntegration.php
│   │   └── LiteSpeedIntegration.php
│   ├── Executor/
│   │   ├── ActionExecutor.php
│   │   ├── OperationFactory.php
│   │   └── ErrorHandler.php
│   └── API/
│       └── REST_API.php
├── templates/
│   ├── admin-dashboard.php
│   ├── chat-interface.php
│   ├── action-card.php
│   ├── setup-wizard.php
│   └── plugin-detector.php
├── database/
│   ├── migrations.php
│   └── schema.sql
└── README.md
```

#### 1.2 Database Schema

WordPress Tables da Creare:

```sql
-- wp_creator_chats
CREATE TABLE wp_creator_chats (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  title VARCHAR(255),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  status ENUM('active', 'archived') DEFAULT 'active',
  FOREIGN KEY (user_id) REFERENCES wp_users(ID)
);

-- wp_creator_messages
CREATE TABLE wp_creator_messages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chat_id INT NOT NULL,
  user_id INT NOT NULL,
  role ENUM('user', 'assistant') DEFAULT 'user',
  content LONGTEXT,
  type ENUM('text', 'action', 'error', 'info') DEFAULT 'text',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  metadata JSON,
  FOREIGN KEY (chat_id) REFERENCES wp_creator_chats(id),
  FOREIGN KEY (user_id) REFERENCES wp_users(ID)
);

-- wp_creator_actions
CREATE TABLE wp_creator_actions (
  id INT PRIMARY KEY AUTO_INCREMENT,
  message_id INT NOT NULL,
  action_type VARCHAR(255),
  target VARCHAR(255),
  status ENUM('pending', 'executing', 'completed', 'failed') DEFAULT 'pending',
  error_message LONGTEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME,
  snapshot_id INT,
  FOREIGN KEY (message_id) REFERENCES wp_creator_messages(id)
);

-- wp_creator_snapshots
CREATE TABLE wp_creator_snapshots (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chat_id INT NOT NULL,
  message_id INT,
  action_id INT,
  snapshot_type ENUM('DELTA') DEFAULT 'DELTA',
  operations JSON,
  storage_file VARCHAR(500),
  storage_size_kb INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  deleted BOOLEAN DEFAULT FALSE,
  deleted_at DATETIME,
  FOREIGN KEY (chat_id) REFERENCES wp_creator_chats(id),
  FOREIGN KEY (message_id) REFERENCES wp_creator_messages(id),
  FOREIGN KEY (action_id) REFERENCES wp_creator_actions(id)
);

-- wp_creator_audit_log
CREATE TABLE wp_creator_audit_log (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  action VARCHAR(255),
  operation_id INT,
  details JSON,
  ip_address VARCHAR(45),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  status ENUM('success', 'failure', 'warning') DEFAULT 'success',
  FOREIGN KEY (user_id) REFERENCES wp_users(ID),
  INDEX (created_at),
  INDEX (user_id)
);

-- wp_creator_backups
CREATE TABLE wp_creator_backups (
  id INT PRIMARY KEY AUTO_INCREMENT,
  chat_id INT NOT NULL,
  file_path VARCHAR(500),
  file_size_kb INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME,
  FOREIGN KEY (chat_id) REFERENCES wp_creator_chats(id)
);
```

#### 1.3 Setup Wizard

On plugin activation, display:

```
┌──────────────────────────────────────────┐
│  Creator Core - Setup Wizard             │
├──────────────────────────────────────────┤
│                                          │
│  Step 1 of 4: Plugin Dependencies        │
│                                          │
│  ✅ Elementor                            │
│     Version: 3.16.0 (Installed)          │
│                                          │
│  ✅ WP Code                              │
│     Version: 2.4.1 (Installed)           │
│                                          │
│  ⚠️  ACF (Recommended)                   │
│     Status: Not Installed                │
│     [Install Now] [Skip]                 │
│                                          │
│  ⚠️  Rank Math (Recommended)             │
│     Status: Not Installed                │
│     [Install Now] [Skip]                 │
│                                          │
│  ⚠️  WooCommerce (Recommended)           │
│     Status: Not Installed                │
│     [Install Now] [Skip]                 │
│                                          │
│  ⚠️  LiteSpeed Cache (Recommended)       │
│     Status: Not Installed                │
│     [Install Now] [Skip]                 │
│                                          │
│                    [Next: Configure Backup]
└──────────────────────────────────────────┘
```

#### 1.4 Admin Dashboard

Location: WordPress Admin → Creator Dashboard

```
┌────────────────────────────────────────┐
│  Creator Dashboard                     │
├────────────────────────────────────────┤
│                                        │
│  [← Back]        [Settings] [Help]    │
│                                        │
│  ┌─ Recent Chats ─────────────────┐   │
│  │ • Chat 001 (Today 09:15)        │   │
│  │ • Chat 002 (Yesterday)          │   │
│  │ • Chat 003 (2 days ago)         │   │
│  │                                 │   │
│  │ [+ New Chat]                    │   │
│  └─────────────────────────────────┘   │
│                                        │
│  ┌─ Quick Stats ──────────────────┐   │
│  │ • Total Tokens Used: 45,231    │   │
│  │ • Actions Completed: 127        │   │
│  │ • Backup Size: 324 MB           │   │
│  │ • Last Action: 5 min ago        │   │
│  └─────────────────────────────────┘   │
│                                        │
│  ┌─ Active Integrations ──────────┐   │
│  │ ✅ Elementor                    │   │
│  │ ✅ WP Code                      │   │
│  │ ✅ ACF                          │   │
│  │ ✅ Rank Math                    │   │
│  │ ❌ WooCommerce (not installed)  │   │
│  │ ❌ LiteSpeed (not installed)    │   │
│  └─────────────────────────────────┘   │
│                                        │
└────────────────────────────────────────┘
```

#### 1.5 Chat Interface

Location: Creator → New Chat

```
┌────────────────────────────────────────┐
│  Creator Chat - Chat #001              │
├────────────────────────────────────────┤
│                                        │
│  [Message History]                     │
│                                        │
│  User: "Create an about page..."       │
│  11:15 AM                              │
│                                        │
│  Assistant: "I need some details..."   │
│  [Clarification Questions]             │
│  - What style?                         │
│  - Include team members?               │
│  11:16 AM                              │
│                                        │
│  User: "Modern, yes team members"      │
│  11:17 AM                              │
│                                        │
│  Assistant: "Creating page..."         │
│  [Processing steps]                    │
│  11:18 AM                              │
│                                        │
│  ┌─────────────────────────────────┐   │
│  │ ⚙️ ACTION COMPLETED             │   │
│  ├─────────────────────────────────┤   │
│  │ Created Page: About Us          │   │
│  │ Status: ✅ Success              │   │
│  │ Validation: ✅ OK               │   │
│  │                                 │   │
│  │ [↶ Undo] [→ Open in Elementor] │   │
│  └─────────────────────────────────┘   │
│                                        │
│  ┌────────────────────────────────┐    │
│  │ [Type your message...]         │    │
│  │                           [Send]    │
│  └────────────────────────────────┘    │
│                                        │
└────────────────────────────────────────┘
```

#### 1.6 Backup & Snapshot System

Delta Snapshot file structure:

```json
// File: /wp-content/uploads/creator-backups/2025-11-26/chat_001/snapshot_msg_1.json

{
  "snapshot_id": 1,
  "chat_id": "chat_001",
  "message_id": 1,
  "timestamp": "2025-11-26T09:15:30Z",
  "operations": [
    {
      "type": "create_post",
      "target": "post_123",
      "status": "completed",
      "before": null,
      "after": {
        "post_id": 123,
        "post_title": "About Us",
        "post_type": "page",
        "post_status": "draft"
      }
    },
    {
      "type": "add_elementor_widget",
      "target": "post_123",
      "status": "completed",
      "before": {...},
      "after": {...}
    }
  ],
  "rollback_instructions": [
    "DELETE FROM wp_posts WHERE ID = 123",
    "DELETE FROM wp_postmeta WHERE post_id = 123",
    "DELETE Elementor data for post 123"
  ]
}
```

#### 1.7 Permission System

```php
class CapabilityChecker {
  public function check_operation_requirements($operation_type) {
    $required_caps = [
      'create_post' => ['edit_posts', 'publish_posts'],
      'add_acf_field' => ['manage_options', 'custom_acf_edit'],
      'toggle_rank_math' => ['manage_options'],
      'add_elementor_widget' => ['edit_posts'],
    ];
    
    $operation_caps = $required_caps[$operation_type] ?? [];
    $user_caps = wp_get_current_user()->get_capabilities();
    $missing = array_diff($operation_caps, $user_caps);
    
    if (!empty($missing)) {
      return [
        'allowed' => false,
        'reason' => "Missing: " . implode(', ', $missing),
        'required_role' => 'Administrator'
      ];
    }
    
    return ['allowed' => true];
  }
}
```

#### 1.8 Proxy Client Integration

```php
class ProxyClient {
  
  public function connect_to_proxy() {
    $license_key = get_option('creator_license_key');
    $site_url = get_site_url();
    
    $response = wp_remote_post(
      CREATOR_PROXY_URL . '/api/auth/validate-license',
      [
        'body' => json_encode([
          'license_key' => $license_key,
          'site_url' => $site_url
        ])
      ]
    );
    
    if ($response['success']) {
      update_option('creator_site_token', $response['site_token']);
      return true;
    }
    
    return false;
  }
  
  public function send_to_ai($prompt, $task_type = 'TEXT_GEN') {
    $response = wp_remote_post(
      CREATOR_PROXY_URL . '/api/ai/route-request',
      [
        'headers' => [
          'Authorization' => 'Bearer ' . get_option('creator_site_token'),
          'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
          'task_type' => $task_type,
          'prompt' => $prompt,
          'context' => $this->get_site_context()
        ])
      ]
    );
    
    return json_decode($response['body']);
  }
  
  private function detect_integrations() {
    return [
      'elementor' => class_exists('Elementor\Plugin'),
      'acf' => class_exists('ACF'),
      'rank_math' => function_exists('rank_math'),
      'woocommerce' => class_exists('WooCommerce'),
      'wp_code' => function_exists('wp_code_get_snippets'),
      'litespeed_cache' => defined('LSCWP_V')
    ];
  }
}
```

#### 1.9 Settings Page

Location: WordPress Admin → Creator Settings

Options:
- API Configuration (Proxy URL, License Key)
- Backup Settings (Location, Max Size, Cleanup Policy)
- Integration Settings (Show detected plugins)
- User Permissions (Roles that can use Creator)
- Advanced (Debug Mode, Log Level, Clear Backups)

#### 1.10 Context Collector

```php
class ContextCollector {
  public function get_wordpress_context() {
    return [
      'site_info' => [
        'site_title' => get_bloginfo('name'),
        'site_url' => get_site_url(),
        'wordpress_version' => get_bloginfo('version')
      ],
      'theme_info' => [
        'theme_name' => wp_get_theme()->get('Name'),
        'theme_author' => wp_get_theme()->get('Author')
      ],
      'active_plugins' => array_map(
        fn($plugin) => get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin),
        get_option('active_plugins')
      ),
      'integrations' => $this->detect_integrations(),
      'current_user' => [
        'id' => get_current_user_id(),
        'email' => wp_get_current_user()->user_email,
        'role' => implode(',', wp_get_current_user()->roles)
      ]
    ];
  }
}
```

#### 1.11 Audit Logging

Every operation logged to `wp_creator_audit_log`:
```php
class AuditLogger {
  public function log($action_type, $result, $validation) {
    // Insert into wp_creator_audit_log
    // Store: user_id, action, status, details, IP address, timestamp
  }
}
```

#### 1.12 Unit Tests

Create tests for:
- Database initialization
- Plugin detection
- Capability checking
- Snapshot creation/rollback
- Audit logging
- API communication

### Deliverables Checklist
- ✅ Plugin scaffolding completo (all files)
- ✅ Database schema e migrations
- ✅ Setup wizard con plugin detector
- ✅ Admin dashboard UI
- ✅ Chat interface UI
- ✅ Settings page
- ✅ Delta backup system (JSON storage)
- ✅ Capability checking system
- ✅ Audit logging system
- ✅ Proxy client integration
- ✅ Context collector
- ✅ Unit tests per ogni componente
- ✅ Database initialization on activation

---

## 📋 Implementation Notes

### On Plugin Activation
1. ✅ Display setup wizard
2. ✅ Check for required plugins (Elementor, WP Code)
3. ✅ Offer installation for optional plugins
4. ✅ Create /wp-content/uploads/creator-backups/ directory
5. ✅ Create WordPress tables (wp_creator_*)
6. ✅ Create "Creator Admin" custom role
7. ✅ Initialize proxy client connection

### Code Standards
- Modern PHP (7.4+)
- PSR-4 autoloading
- Security: sanitize input, verify nonces
- Accessibility: WCAG 2.1 Level AA
- Responsive design (mobile-first)

### Critical Requirements
- ❌ NO TODOs, placeholders, or incomplete code
- ❌ NO hardcoded values (use options/constants)
- ✅ All features working end-to-end
- ✅ Ready for deployment
- ✅ Fully tested on target WordPress

### Separation of Concerns
- **Admin/** → Dashboard & Settings UI
- **Chat/** → Chat interface & messaging
- **Backup/** → Snapshot management & rollback
- **Permission/** → Capability checking & roles
- **Audit/** → Logging & operation tracking
- **Integrations/** → Plugin detection & communication
- **Executor/** → Action execution (MILESTONE 2)
- **API/** → REST endpoints (MILESTONE 3)

---

## 🧪 Testing & Deployment

### Local Testing Checklist
- [ ] Plugin activates without errors
- [ ] Database tables created successfully
- [ ] Setup wizard displays correctly
- [ ] Admin dashboard accessible
- [ ] Chat interface functional
- [ ] Backup directory created
- [ ] Settings saved correctly
- [ ] All class methods callable
- [ ] No PHP warnings/errors in debug log

### Target Environment Testing
Before deployment to production (https://micheleb174.sg-host.com):

1. **On Dev/Staging:**
   - [ ] Test full plugin installation
   - [ ] Test setup wizard with all plugin combinations
   - [ ] Test dashboard UI rendering
   - [ ] Test database operations
   - [ ] Test snapshot creation/rollback
   - [ ] Test capability checking
   - [ ] Test audit logging
   - [ ] Test Proxy client connection

2. **Integration Testing:**
   - [ ] Verify Elementor detection
   - [ ] Verify WP Code detection
   - [ ] Verify ACF detection (if installed)
   - [ ] Verify Rank Math detection (if installed)
   - [ ] Verify WooCommerce detection (if installed)
   - [ ] Verify LiteSpeed Cache detection (if installed)

3. **Security Testing:**
   - [ ] Nonce verification working
   - [ ] Input sanitization working
   - [ ] Capability checking enforced
   - [ ] SQL injection prevention verified
   - [ ] XSS prevention verified

### Deployment to Production

```bash
# 1. Clone to target WordPress
cd /wp-content/plugins/
git clone https://github.com/michelebogoni/creator-core-plugin.git creator-core

# 2. Install via WordPress admin
# Go to: WordPress Admin → Plugins → Activate "Creator Core"

# 3. Complete Setup Wizard
# Follow on-screen instructions

# 4. Verify installation
# - Check: WordPress Admin → Creator → Dashboard
# - Check: Database tables exist (wp_creator_*)
# - Check: Backup directory exists (/wp-content/uploads/creator-backups/)
```

### Rollback Procedure

If issues occur:
```bash
# 1. Deactivate plugin
WordPress Admin → Plugins → Deactivate "Creator Core"

# 2. Keep data intact (optional)
# Database tables remain for later inspection

# 3. Delete plugin files (if complete removal needed)
rm -rf /wp-content/plugins/creator-core/

# 4. Restore from backup (if available)
# Use WordPress backup or server snapshot
```

---

## 📝 Next Steps (MILESTONE 2 & 3)

After MILESTONE 1 is complete:

**MILESTONE 2:** Action Executor
- Implement 30+ WordPress operations
- Create auto-test system
- Build plugin/WP Code/Pure WP fallback logic

**MILESTONE 3:** AI Integration
- Connect to Firebase Proxy
- Implement 3-level confidence system
- Handle AI routing and responses

---

## 📊 Project Status

| Component | FASE 1 | FASE 2-M1 | Status |
|-----------|--------|-----------|--------|
| Firebase Proxy | ✅ | - | COMPLETE |
| WordPress Plugin | - | 🔨 | IN PROGRESS |
| AI Integration | - | - | PLANNED |

**Current Focus:** MILESTONE 1 - Core Infrastructure

**Repository:** https://github.com/michelebogoni/creator-core-plugin

---

**Version:** 2.2 | **Last Updated:** 2025-11-26 | **Status:** Ready for Claude Code Implementation