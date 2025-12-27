# Creator - Project Structure Documentation

> **Version:** 3.0.0-MVP
> **Last Updated:** December 2025
> **Architecture:** WordPress Plugin + Firebase Cloud Functions

---

## Overview

Creator is an AI-powered WordPress development assistant. The project consists of two main components:

1. **WordPress Plugin** (`creator-core`) - Client-side plugin that provides the chat interface and code execution
2. **Firebase Cloud Functions** - Server-side proxy for AI providers (Gemini, Claude) with authentication and rate limiting

---

## Root Directory

```
/creator/
```

| File | Description |
|------|-------------|
| `.firebaserc` | Firebase project configuration - links to the Firebase project ID |
| `.gitignore` | Git ignore rules for the repository |
| `claude.md` | Complete MVP specification document in Italian - defines architecture, workflows, and system prompts |
| `database.rules.json` | Firebase Realtime Database security rules |
| `firebase.json` | Firebase deployment configuration for hosting and functions |
| `firestore.indexes.json` | Firestore composite index definitions for optimized queries |
| `firestore.rules` | Firestore security rules defining read/write permissions |

---

## /docs/

Project-level documentation.

| File | Description |
|------|-------------|
| `FIREBASE_ARCHITECTURE.md` | Detailed Firebase system architecture - Cloud Functions, Firestore collections, API endpoints, and data flows |
| `FIREBASE_DEPLOYMENT_GUIDE.md` | Step-by-step deployment instructions for Firebase including secrets configuration and Node.js requirements |
| `PHASE-6-SESSION-HISTORY.md` | Implementation history and session notes from Phase 6 development |

---

## /public/

Firebase Hosting static files.

| File | Description |
|------|-------------|
| `index.html` | Static landing page served by Firebase Hosting - placeholder for the Creator website |

---

## /functions/

Firebase Cloud Functions backend (TypeScript).

### Configuration Files

| File | Description |
|------|-------------|
| `.eslintrc.js` | ESLint configuration for TypeScript linting rules |
| `package.json` | Node.js dependencies and npm scripts (build, deploy, test, lint) |
| `tsconfig.json` | TypeScript compiler configuration |

---

### /functions/src/

TypeScript source code for Cloud Functions.

| File | Description |
|------|-------------|
| `index.ts` | **Main entry point** - Exports all Cloud Functions for Firebase deployment. Defines 8 HTTP endpoints for authentication, AI routing, and plugin documentation |

---

### /functions/src/api/

HTTP API endpoint handlers.

#### /functions/src/api/ai/

| File | Description |
|------|-------------|
| `routeRequest.ts` | **POST /api/ai/route-request** - Main AI generation endpoint. Routes requests to Gemini or Claude with automatic fallback. Handles authentication, rate limiting, token counting, cost tracking, and audit logging |

#### /functions/src/api/auth/

| File | Description |
|------|-------------|
| `validateLicense.ts` | **POST /api/auth/validate-license** - License validation endpoint. Verifies license keys, generates JWT tokens for authenticated sessions, and records audit logs |

#### /functions/src/api/plugin-docs/

| File | Description |
|------|-------------|
| `pluginDocs.ts` | Plugin documentation repository endpoints. Contains 6 functions: `getPluginDocsApi` (GET docs from cache), `savePluginDocsApi` (POST save docs), `getPluginDocsStatsApi` (GET repository stats), `getPluginDocsAllVersionsApi` (GET all versions), `researchPluginDocsApi` (POST AI research), `syncPluginDocsApi` (POST sync to WordPress) |

---

### /functions/src/config/

Configuration constants and models.

| File | Description |
|------|-------------|
| `models.ts` | **AI Models Configuration** - Single source of truth for AI model IDs and pricing. Defines Gemini 2.5 Pro and Claude Opus 4.5 configurations. Contains helper functions: `isValidModel()`, `getModelPricing()`, `getPrimaryModel()`, `isValidProvider()` |

---

### /functions/src/lib/

Utility libraries and helpers.

| File | Description |
|------|-------------|
| `index.ts` | Barrel export for all library modules |
| `firestore.ts` | **Firestore database operations** - CRUD operations for licenses, audit logs, rate limits, cost tracking, and plugin docs cache. Includes functions: `getLicenseByKey()`, `updateLicense()`, `incrementTokensUsed()`, `createAuditLog()`, `getRateLimitCount()`, `incrementRateLimitCounter()`, `checkAndIncrementRateLimit()`, `updateCostTracking()`, `getCostTracking()`, `normalizePluginVersion()` (X.Y version matching), analytics functions, and plugin docs operations |
| `jwt.ts` | **JWT handling** - Token creation and verification for site authentication. Functions: `createToken()`, `verifyToken()`, `extractBearerToken()` |
| `jwt.test.ts` | Unit tests for JWT operations |
| `logger.ts` | **Structured logging** - Creates Logger instances with context for Cloud Functions logging |
| `secrets.ts` | **Google Cloud Secrets** - Defines secret references for API keys (Gemini, Claude, JWT) using Firebase Functions params |

---

### /functions/src/middleware/

HTTP middleware for request processing.

| File | Description |
|------|-------------|
| `index.ts` | Barrel export for middleware modules |
| `auth.ts` | **Authentication middleware** - Validates JWT tokens, verifies license status, checks site URL matching. Functions: `authenticateRequest()`, `sendAuthErrorResponse()` |
| `rateLimit.ts` | **Rate limiting middleware** - Implements per-IP rate limiting with configurable windows and limits. Functions: `checkRateLimit()`, `RateLimitConfig` interface |
| `rateLimit.test.ts` | Unit tests for rate limiting |

---

### /functions/src/providers/

AI provider implementations.

| File | Description |
|------|-------------|
| `README.md` | Documentation for implementing AI providers |
| `index.ts` | Barrel export for provider modules |
| `claude.ts` | **Anthropic Claude provider** - Implements `AIProvider` interface for Claude API. Handles message formatting, token counting with tiktoken, streaming, and error handling |
| `gemini.ts` | **Google Gemini provider** - Implements `AIProvider` interface for Gemini API. Handles content generation, safety settings, token counting, and error handling |

---

### /functions/src/services/

Business logic services.

| File | Description |
|------|-------------|
| `README.md` | Documentation for service implementations |
| `index.ts` | Barrel export for service modules |
| `licensing.ts` | **License validation service** - Business logic for license verification, quota checking, and status validation. Functions: `validateLicenseKey()`, `checkQuota()`, `isLicenseExpired()` |
| `licensing.test.ts` | Unit tests for licensing service |
| `modelService.ts` | **AI Model routing service** - Orchestrates AI requests with primary/fallback logic. Selects provider based on request, handles retries, and manages fallback to secondary provider |
| `pluginDocsResearch.ts` | **Plugin documentation research** - Uses Claude AI to research comprehensive WordPress plugin documentation when not in cache. Includes `PluginDocsResearchService` class, research prompts for code examples, best practices, and data structures. Normalizes versions to X.Y format |

---

### /functions/src/types/

TypeScript type definitions.

| File | Description |
|------|-------------|
| `index.ts` | Barrel export for all type modules |
| `AIProvider.ts` | `AIProvider` interface - Contract for AI provider implementations with `generate()` method |
| `APIResponse.ts` | `AuditLogEntry` and `RateLimitCounter` interfaces for API responses and logging |
| `Auth.ts` | Authentication-related types for request/response structures |
| `JWTClaims.ts` | `JWTClaims` interface - Structure of JWT token payload (license_id, site_url, plan, etc.) |
| `Job.test.ts` | Unit tests for Job types |
| `License.ts` | `License`, `LicensePlan`, `LicenseStatus`, `UpdateLicenseData` types for license management |
| `ModelConfig.ts` | `ModelConfig`, `ModelPricing` types for AI model configuration |
| `PluginDocs.ts` | Types for plugin documentation: `PluginDocsEntry`, `CreatePluginDocsData`, `PluginDocsStats`, `ResearchPluginDocsRequest`, `ResearchPluginDocsResponse`, `SavePluginDocsRequest`, `SyncPluginDocsRequest`, etc. |
| `Route.ts` | `RouteRequest`, `RouteResponse` types for AI routing requests |

---

### /functions/src/utils/

Utility functions.

| File | Description |
|------|-------------|
| `promptUtils.ts` | Prompt validation and sanitization utilities for AI requests |

---

### /functions/lib/

Compiled JavaScript output (auto-generated from TypeScript).

Contains `.js`, `.d.ts`, `.js.map`, and `.d.ts.map` files mirroring the `src/` structure. These are deployment artifacts and should not be edited directly.

---

## /packages/creator-core-plugin/creator-core/

WordPress plugin source code.

### Root Plugin Files

| File | Description |
|------|-------------|
| `.gitignore` | Git ignore rules for the plugin |
| `composer.json` | PHP dependencies configuration (currently no external dependencies) |
| `creator-core.php` | **Main plugin file** - Plugin header, constants, requirement checks, autoloader registration, and initialization hooks. Defines `CREATOR_CORE_VERSION`, `CREATOR_CORE_PATH`, `CREATOR_CORE_URL` |
| `phpunit.xml` | PHPUnit test configuration |
| `uninstall.php` | Cleanup script executed when plugin is uninstalled - removes options, tables, and cached data |

---

### /assets/css/

Stylesheets for admin interface.

| File | Description |
|------|-------------|
| `admin-common.css` | Common styles shared across admin pages - buttons, cards, status indicators, form elements |
| `chat-interface.css` | Chat interface styles - message bubbles, input area, typing indicators, action cards, debug panel |

---

### /assets/js/

JavaScript for admin functionality.

| File | Description |
|------|-------------|
| `action-handler.js` | Handles execution of AI-suggested actions - confirmation dialogs, undo functionality, action status updates |
| `chat-interface.js` | Main chat interface logic - message sending, response rendering, conversation management, auto-loop handling, markdown rendering |
| `debug-panel.js` | Debug panel functionality - shows raw AI responses, request/response data, conversation history |

---

### /database/

Database schema and migrations.

| File | Description |
|------|-------------|
| `migrations.php` | Database migration system - version tracking, table creation/updates, rollback support |
| `schema.sql` | SQL schema definitions for plugin tables (conversations, messages, snapshots, audit logs) |

---

### /docs/

Plugin-specific documentation.

| File | Description |
|------|-------------|
| `PRODUCTION_READINESS.md` | Production readiness checklist - security considerations, performance optimizations, deployment steps |
| `SYSTEM_PROMPTS.md` | AI system prompts documentation - defines how Creator communicates with AI, response formats, and behavior guidelines |
| `TEST_SCENARIOS.md` | Test case documentation - manual and automated test scenarios for various features |

---

### /includes/

PHP classes organized by namespace.

| File | Description |
|------|-------------|
| `Activator.php` | `CreatorCore\Activator` - Plugin activation hook handler. Creates database tables, sets default options, runs migrations |
| `Autoloader.php` | `CreatorCore\Autoloader` - PSR-4 autoloader for plugin classes. Maps namespaces to directories |
| `Deactivator.php` | `CreatorCore\Deactivator` - Plugin deactivation hook handler. Cleans up scheduled events |
| `Loader.php` | `CreatorCore\Loader` - Main plugin bootstrap class. Initializes all components, registers hooks, and starts the plugin |

#### /includes/Admin/

| File | Description |
|------|-------------|
| `Settings.php` | `CreatorCore\Admin\Settings` - Admin settings page handler. Renders settings UI, handles AJAX for license validation, saves configuration options |

#### /includes/Chat/

| File | Description |
|------|-------------|
| `ChatController.php` | `CreatorCore\Chat\ChatController` - REST API controller for chat endpoint. Handles `POST /wp-json/creator/v1/chat`, validates requests, orchestrates conversation flow |
| `ChatInterface.php` | `CreatorCore\Chat\ChatInterface` - Registers admin menu page, enqueues assets, renders chat interface HTML directly (no template) |

#### /includes/Context/

| File | Description |
|------|-------------|
| `ContextLoader.php` | `CreatorCore\Context\ContextLoader` - Collects WordPress environment context. Gathers WordPress version, PHP version, theme info, active plugins, site URLs for AI requests |

#### /includes/Conversation/

| File | Description |
|------|-------------|
| `ConversationManager.php` | `CreatorCore\Conversation\ConversationManager` - Orchestrates multi-step conversations with AI. Implements the 4-phase loop: Discovery → Strategy → Implementation → Verification |

#### /includes/Debug/

| File | Description |
|------|-------------|
| `DebugController.php` | `CreatorCore\Debug\DebugController` - REST API endpoints for debug functionality. Provides conversation history, message details, and debug log access |
| `DebugLogger.php` | `CreatorCore\Debug\DebugLogger` - Logs conversations and AI interactions to files for debugging. Supports log rotation and size limits |

#### /includes/Execution/

| File | Description |
|------|-------------|
| `WPCLIExecutor.php` | `CreatorCore\Execution\WPCLIExecutor` - Executes WP-CLI commands from AI responses. Validates commands, handles output capture, and error reporting |

#### /includes/Executor/

| File | Description |
|------|-------------|
| `CodeExecutor.php` | `CreatorCore\Executor\CodeExecutor` - Executes PHP code from AI responses via `eval()`. Validates code against forbidden functions, handles errors, captures output. Functions: `execute()`, `validate_code()`, `prepare_code()`, `execute_with_timeout()` |

#### /includes/Proxy/

| File | Description |
|------|-------------|
| `ProxyClient.php` | `CreatorCore\Proxy\ProxyClient` - HTTP client for Firebase communication. Handles license validation, AI request routing, JWT token management, and error handling |

#### /includes/Response/

| File | Description |
|------|-------------|
| `ResponseHandler.php` | `CreatorCore\Response\ResponseHandler` - Parses and handles AI responses. Processes different response types: `question`, `plan`, `roadmap`, `execute`, `verify`, `complete`, `error`, `request_docs`. Executes code and manages action flow |

---

### /tests/

PHPUnit test files.

| File | Description |
|------|-------------|
| `bootstrap.php` | Test bootstrap - loads WordPress stubs and autoloader for testing |

#### /tests/stubs/

| File | Description |
|------|-------------|
| `wordpress-stubs.php` | WordPress function stubs for unit testing without WordPress loaded |

#### /tests/Unit/

Unit tests for individual classes.

| File | Description |
|------|-------------|
| `ActionDispatcherTest.php` | Tests for action dispatching logic |
| `ActionResultTest.php` | Tests for action result handling |
| `AuditLoggerTest.php` | Tests for audit logging functionality |
| `CapabilityCheckerTest.php` | Tests for capability checking |
| `ChatInterfaceTest.php` | Tests for chat interface class |
| `ContextCollectorTest.php` | Tests for context collection |
| `ExecutePHPHandlerTest.php` | Tests for PHP execution handler |
| `PluginDetectorTest.php` | Tests for plugin detection |
| `ProxyClientTest.php` | Tests for Firebase proxy client |
| `RollbackTest.php` | Tests for rollback functionality |
| `SnapshotManagerTest.php` | Tests for snapshot management |

#### /tests/Integration/

Integration tests for component interaction.

| File | Description |
|------|-------------|
| `CodeExecutorIntegrationTest.php` | Integration tests for code execution |
| `ConversationHistoryTest.php` | Tests for conversation history persistence |
| `ErrorScenarioTest.php` | Tests for various error scenarios |
| `FileAttachmentTest.php` | Tests for file attachment handling |
| `LazyLoadContextTest.php` | Tests for lazy-loaded context |
| `PhaseDetectorIntegrationTest.php` | Tests for phase detection in conversations |
| `RollbackIntegrationTest.php` | Integration tests for rollback |
| `UniversalPHPEngineTest.php` | Tests for PHP code execution engine |

---

## Architecture Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                      WordPress Site                              │
├─────────────────────────────────────────────────────────────────┤
│  Creator Plugin (creator-core)                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │ ChatInterface│→ │ChatController│→ │ ConversationManager  │  │
│  │   (UI)       │  │  (REST API)  │  │   (Orchestration)    │  │
│  └──────────────┘  └──────────────┘  └──────────────────────┘  │
│         │                                      │                 │
│         ↓                                      ↓                 │
│  ┌──────────────┐                    ┌──────────────────────┐  │
│  │ContextLoader │                    │  ResponseHandler     │  │
│  │(WP Context)  │                    │  (Parse & Execute)   │  │
│  └──────────────┘                    └──────────────────────┘  │
│         │                                      │                 │
│         └────────────┬─────────────────────────┘                 │
│                      ↓                                           │
│              ┌──────────────┐                                    │
│              │ ProxyClient  │←────── JWT Token                   │
│              │ (HTTP)       │                                    │
│              └──────────────┘                                    │
└────────────────────┬────────────────────────────────────────────┘
                     │ HTTPS
                     ↓
┌─────────────────────────────────────────────────────────────────┐
│                  Firebase Cloud Functions                        │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │validateLicense│  │ routeRequest │  │   pluginDocs APIs   │  │
│  │  (Auth)      │  │  (AI Proxy)  │  │   (Documentation)   │  │
│  └──────────────┘  └──────────────┘  └──────────────────────┘  │
│         │                │                                       │
│         ↓                ↓                                       │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Middleware                             │   │
│  │  auth.ts (JWT) │ rateLimit.ts (Rate Limiting)            │   │
│  └──────────────────────────────────────────────────────────┘   │
│         │                │                                       │
│         ↓                ↓                                       │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Services                               │   │
│  │  ModelService (Routing) │ PluginDocsResearch (AI Docs)   │   │
│  └──────────────────────────────────────────────────────────┘   │
│         │                │                                       │
│         ↓                ↓                                       │
│  ┌──────────────┐  ┌──────────────┐                             │
│  │ Gemini.ts    │  │ Claude.ts    │  ← AI Providers             │
│  │ (Google)     │  │ (Anthropic)  │                             │
│  └──────────────┘  └──────────────┘                             │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    Firestore                              │   │
│  │  licenses │ audit_logs │ rate_limits │ plugin_docs_cache │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## File Count Summary

| Category | Count |
|----------|-------|
| TypeScript Source | 30 |
| PHP Classes | 15 |
| PHP Tests | 19 |
| JavaScript | 3 |
| CSS | 2 |
| Configuration | 8 |
| Documentation | 6 |
| SQL | 1 |
| HTML | 1 |
| **Total (source)** | **85** |

---

*Generated on December 2025*
