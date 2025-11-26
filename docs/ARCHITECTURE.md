# Creator - Architecture Overview

## System Overview

Creator is an AI-powered WordPress development assistant composed of two main components:

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           ARCHITECTURE                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────────┐         ┌──────────────────┐                      │
│  │   WordPress      │  HTTPS  │    Firebase      │  API Calls           │
│  │   Plugin         │ ──────► │    Functions     │ ───────────►         │
│  │   (Creator Core) │         │    (AI Proxy)    │                      │
│  └──────────────────┘         └──────────────────┘                      │
│         │                              │                                 │
│         │                              │         ┌──────────────────┐   │
│         ▼                              │         │   Anthropic      │   │
│  ┌──────────────────┐                  ├────────►│   (Claude)       │   │
│  │   WordPress      │                  │         └──────────────────┘   │
│  │   Database       │                  │         ┌──────────────────┐   │
│  │   (Operations)   │                  ├────────►│   OpenAI         │   │
│  └──────────────────┘                  │         │   (GPT)          │   │
│                                        │         └──────────────────┘   │
│                                        │         ┌──────────────────┐   │
│                                        └────────►│   Google         │   │
│                                                  │   (Gemini)       │   │
│                                                  └──────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

## Components

### 1. Firebase Functions (AI Proxy)

**Location:** `functions/`

**Purpose:** Secure middleware between WordPress plugin and AI providers.

**Responsibilities:**
- License validation and management
- API key security (keys never exposed to client)
- Request routing to appropriate AI provider
- Rate limiting and cost tracking
- Usage analytics
- Job queue for async operations

**Key Functions:**
| Function | Region | Description |
|----------|--------|-------------|
| `validateLicense` | europe-west1 | Validates plugin licenses |
| `routeRequest` | us-central1 | Routes AI requests to providers |
| `submitTask` | us-central1 | Submits async tasks to queue |
| `getTaskStatus` | us-central1 | Checks async task status |
| `getAnalytics` | us-central1 | Returns usage analytics |
| `processJobQueue` | europe-west1 | Processes async job queue |

**Tech Stack:**
- Node.js 20
- TypeScript
- Firebase Functions v2
- Firestore (data persistence)
- Secret Manager (API keys)

### 2. WordPress Plugin (Creator Core)

**Location:** `packages/creator-core-plugin/creator-core/`

**Purpose:** WordPress admin interface for AI-assisted development.

**Responsibilities:**
- Chat interface for AI interaction
- Action execution on WordPress site
- Backup/rollback system
- Plugin integrations (Elementor, WooCommerce, etc.)
- Audit logging

**Key Components:**
| Component | Description |
|-----------|-------------|
| `ChatInterface` | Real-time chat with AI |
| `ActionExecutor` | Executes AI-suggested actions |
| `SnapshotManager` | Creates backups before changes |
| `ProxyClient` | Communicates with Firebase proxy |
| `PluginDetector` | Detects installed plugins |

**Tech Stack:**
- PHP 7.4+
- WordPress 6.0+
- PSR-4 Autoloading
- Composer

## Data Flow

### 1. User Sends Message

```
User Input → ChatInterface → ProxyClient → Firebase Functions → AI Provider
                                   │
                                   ▼
                           License Validation
                                   │
                                   ▼
                            Rate Limiting
                                   │
                                   ▼
                           Provider Selection
```

### 2. AI Response with Action

```
AI Response → ChatInterface → Display Action Card → User Approval
                                                          │
                                                          ▼
                                                   Create Snapshot
                                                          │
                                                          ▼
                                                   Execute Action
                                                          │
                                                          ▼
                                                   Log Operation
```

### 3. Rollback Flow

```
User Requests Rollback → SnapshotManager → Restore Files/DB → Verify → Done
```

## Security Model

### API Key Protection
- AI provider API keys stored in Google Secret Manager
- Never exposed to WordPress plugin or browser
- Accessed only by Firebase Functions

### License Validation
- JWT-based license tokens
- Validated on every request
- Tied to domain/site URL

### WordPress Security
- Nonce verification on all AJAX calls
- Capability checks (manage_options)
- Input sanitization (sanitize_text_field, absint, etc.)
- Output escaping (esc_html, esc_attr, etc.)

### Backup Security
- Backups stored in wp-content/creator-backups/
- Protected with .htaccess
- Sanitized file paths
- Directory traversal prevention

## Scalability

### Firebase Functions
- Auto-scaling based on demand
- Regional deployment (europe-west1, us-central1)
- Async job queue for heavy operations
- Configurable min/max instances

### WordPress Plugin
- Lazy loading of components
- Efficient database queries
- Chunked file operations
- Background processing for large tasks
