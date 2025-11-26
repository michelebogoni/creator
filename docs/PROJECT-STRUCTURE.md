# Creator - Project Structure

## Repository Layout

```
creator/                                    # Root (monorepo)
│
├── functions/                              # Firebase Functions (AI Proxy)
│   ├── src/                                # TypeScript source files
│   │   ├── index.ts                        # Main entry point
│   │   ├── api/                            # API endpoints
│   │   │   ├── license/                    # License validation
│   │   │   ├── proxy/                      # AI provider routing
│   │   │   ├── analytics/                  # Usage analytics
│   │   │   └── tasks/                      # Async task management
│   │   ├── services/                       # Business logic
│   │   │   ├── providers/                  # AI provider clients
│   │   │   ├── queue/                      # Job queue processing
│   │   │   └── cost/                       # Cost tracking
│   │   ├── utils/                          # Utilities
│   │   └── types/                          # TypeScript types
│   ├── tests/                              # Jest tests
│   ├── lib/                                # Compiled JavaScript (gitignored)
│   ├── package.json                        # Dependencies
│   ├── tsconfig.json                       # TypeScript config
│   └── .eslintrc.json                      # ESLint config
│
├── packages/                               # Packages directory
│   └── creator-core-plugin/                # WordPress Plugin package
│       ├── claude.md                       # AI context file
│       └── creator-core/                   # Plugin files (installable)
│           ├── creator-core.php            # Main plugin file
│           ├── uninstall.php               # Cleanup on uninstall
│           ├── composer.json               # PHP dependencies
│           ├── phpunit.xml                 # PHPUnit config
│           ├── includes/                   # PHP classes
│           │   ├── Autoloader.php          # PSR-4 autoloader
│           │   ├── Loader.php              # Hook/filter loader
│           │   ├── Activator.php           # Activation logic
│           │   ├── Deactivator.php         # Deactivation logic
│           │   ├── Admin/                  # Admin UI
│           │   │   ├── Dashboard.php       # Main dashboard
│           │   │   ├── Settings.php        # Settings page
│           │   │   └── SetupWizard.php     # First-run wizard
│           │   ├── API/                    # REST API
│           │   │   └── REST_API.php        # API endpoints
│           │   ├── Audit/                  # Audit logging
│           │   │   ├── AuditLogger.php     # Log operations
│           │   │   └── OperationTracker.php# Track changes
│           │   ├── Backup/                 # Backup system
│           │   │   ├── DeltaBackup.php     # Incremental backups
│           │   │   ├── Rollback.php        # Restore from backup
│           │   │   └── SnapshotManager.php # Snapshot management
│           │   ├── Chat/                   # Chat interface
│           │   │   ├── ChatInterface.php   # UI logic
│           │   │   ├── ContextCollector.php# Gather WP context
│           │   │   └── MessageHandler.php  # Handle messages
│           │   ├── Executor/               # Action execution
│           │   │   ├── ActionExecutor.php  # Execute AI actions
│           │   │   ├── ErrorHandler.php    # Handle errors
│           │   │   └── OperationFactory.php# Create operations
│           │   ├── Integrations/           # Third-party integrations
│           │   │   ├── ProxyClient.php     # Firebase proxy client
│           │   │   ├── PluginDetector.php  # Detect plugins
│           │   │   ├── ElementorIntegration.php
│           │   │   ├── WooCommerceIntegration.php
│           │   │   ├── ACFIntegration.php
│           │   │   └── ...                 # Other integrations
│           │   └── Permission/             # Permission handling
│           │       ├── CapabilityChecker.php
│           │       └── RoleMapper.php
│           ├── templates/                  # PHP templates
│           │   ├── admin-dashboard.php
│           │   ├── chat-interface.php
│           │   ├── settings.php
│           │   └── ...
│           ├── assets/                     # Static assets
│           │   ├── css/                    # Stylesheets
│           │   └── js/                     # JavaScript
│           ├── database/                   # Database files
│           │   ├── schema.sql              # Table definitions
│           │   └── migrations.php          # Migration logic
│           └── tests/                      # PHPUnit tests
│               ├── bootstrap.php           # Test bootstrap
│               ├── stubs/                  # WordPress stubs
│               └── Unit/                   # Unit tests
│
├── .github/                                # GitHub configuration
│   ├── workflows/                          # CI/CD workflows
│   │   ├── firebase-deploy.yml             # Firebase deployment
│   │   ├── test-plugin.yml                 # WordPress plugin tests
│   │   └── deploy-plugin.yml               # Plugin release
│   └── CODEOWNERS                          # Code ownership
│
├── docs/                                   # Documentation
│   ├── ARCHITECTURE.md                     # System architecture
│   ├── DEPLOYMENT.md                       # Deployment guide
│   └── PROJECT-STRUCTURE.md                # This file
│
├── tests/                                  # Root-level tests
│   └── load-testing.js                     # k6 load tests
│
├── firebase.json                           # Firebase configuration
├── .firebaserc                             # Firebase project config
├── firestore.rules                         # Firestore security rules
├── firestore.indexes.json                  # Firestore indexes
├── database.rules.json                     # Realtime DB rules
├── .gitignore                              # Git ignore rules
├── claude.md                               # AI context for repo
└── README.md                               # Main documentation
```

## Key Files

### Root Level

| File | Purpose |
|------|---------|
| `firebase.json` | Firebase project configuration |
| `.firebaserc` | Firebase project aliases |
| `claude.md` | Context file for AI assistants |
| `README.md` | Main project documentation |

### Functions

| File | Purpose |
|------|---------|
| `src/index.ts` | Exports all Cloud Functions |
| `package.json` | Node.js dependencies |
| `tsconfig.json` | TypeScript compiler options |

### WordPress Plugin

| File | Purpose |
|------|---------|
| `creator-core.php` | Plugin header and bootstrap |
| `composer.json` | PHP dependencies |
| `phpunit.xml` | Test configuration |

### Workflows

| File | Triggers On | Purpose |
|------|-------------|---------|
| `firebase-deploy.yml` | Push to `functions/**` | Deploy Firebase Functions |
| `test-plugin.yml` | Push to `packages/**` | Test WordPress plugin |
| `deploy-plugin.yml` | Tag `plugin-v*` | Release plugin to GitHub |

## Development Commands

### Firebase Functions

```bash
cd functions

# Install dependencies
npm install

# Run linter
npm run lint

# Run tests
npm run test

# Build TypeScript
npm run build

# Start emulator
npm run serve

# Deploy
firebase deploy --only functions
```

### WordPress Plugin

```bash
cd packages/creator-core-plugin/creator-core

# Install dependencies
composer install

# Run tests
vendor/bin/phpunit

# Run code sniffer (if configured)
composer run-script phpcs
```

## Naming Conventions

### Files
- PHP: `PascalCase.php` (classes)
- TypeScript: `camelCase.ts`
- CSS/JS: `kebab-case.css`

### Classes
- PHP: `PascalCase` with namespace
- TypeScript: `PascalCase`

### Functions
- PHP: `snake_case`
- TypeScript: `camelCase`

### Database Tables
- Prefix: `creator_`
- Style: `snake_case`
