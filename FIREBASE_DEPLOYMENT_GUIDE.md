# Firebase Deployment Guide - Creator AI Proxy

## Project Information

- **Firebase Project ID**: `creator-ai-proxy`
- **GCP Project Number**: `757337256338`
- **Firebase Console**: https://console.firebase.google.com/project/creator-ai-proxy/overview
- **Cloud Functions Region**: `us-central1` (routeRequest), `europe-west1` (validateLicense)

## Service Accounts

### Active Service Accounts

| Service Account | Purpose |
|----------------|---------|
| `757337256338-compute@developer.gserviceaccount.com` | Default Compute Engine SA - **used for functions with secrets** |
| `creator-ai-proxy@creator-ai-proxy.iam.gserviceaccount.com` | Default App Engine SA |
| `firebase-adminsdk-fbsvc@creator-ai-proxy.iam.gserviceaccount.com` | Firebase Admin SDK |

### Important Note on Service Accounts

Firebase CLI by default tries to use `creator-ai-proxy@appspot.gserviceaccount.com` for functions with secrets, but **this service account doesn't exist** in this project.

**Solution**: All functions that use secrets MUST specify the `serviceAccount` option:

```typescript
export const routeRequest = onRequest(
  {
    secrets: [jwtSecret, geminiApiKey, claudeApiKey],
    cors: true,
    maxInstances: 100,
    timeoutSeconds: 120,
    serviceAccount: "757337256338-compute@developer.gserviceaccount.com",  // REQUIRED!
  },
  async (req, res) => { ... }
);
```

## Secrets Configuration

Secrets are stored in Google Cloud Secret Manager:

| Secret Name | Description |
|-------------|-------------|
| `JWT_SECRET` | JWT signing secret for authentication |
| `GEMINI_API_KEY` | Google Gemini API key |
| `CLAUDE_API_KEY` | Anthropic Claude API key |

### Secret Permissions

The compute service account (`757337256338-compute@developer.gserviceaccount.com`) has `Secret Manager Secret Accessor` role on all secrets. **Do NOT recreate the secrets** - they work correctly.

To verify permissions:
```bash
gcloud secrets get-iam-policy GEMINI_API_KEY --project=creator-ai-proxy
gcloud secrets get-iam-policy CLAUDE_API_KEY --project=creator-ai-proxy
gcloud secrets get-iam-policy JWT_SECRET --project=creator-ai-proxy
```

## Functions Overview

### Gen2 Functions (firebase-functions/v2)
- `routeRequest` - Main AI routing endpoint (us-central1)
- `validateLicense` - License validation (europe-west1)

### Gen1 Functions (firebase-functions v1)
- `getPluginDocsApi` - Get plugin docs from cache
- `savePluginDocsApi` - Save plugin docs to cache
- `getPluginDocsStatsApi` - Get cache statistics
- `getPluginDocsAllVersionsApi` - Get all versions for a plugin
- `researchPluginDocsApi` - AI-powered plugin research
- `syncPluginDocsApi` - Sync plugin docs to WordPress

## Node Version Requirement

**CRITICAL**: The project requires Node.js 20.

Firebase Cloud Build uses Node 20 to build functions. If you generate `package-lock.json` with a different Node version (e.g., Node 24), the deployment will fail with:

```
npm error `npm ci` can only install packages when your package.json and package-lock.json are in sync.
npm error Missing: picomatch@4.0.3 from lock file
```

### Solution: Use Node 20

Install nvm (Node Version Manager) if not already installed:
```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.zshrc  # or source ~/.bashrc
```

Switch to Node 20:
```bash
nvm install 20
nvm use 20
```

## Deployment Workflow

### 1. Sync with GitHub

```bash
cd /Users/michele/creator-ai-proxy

# Fetch the branch you're working on
git fetch origin <branch-name>

# Reset to remote (discards local changes)
git reset --hard origin/<branch-name>

# OR merge remote changes
git pull origin <branch-name> --no-rebase
```

### 2. Prepare for Deployment

```bash
cd functions

# IMPORTANT: Use Node 20!
nvm use 20

# Clean install
rm -rf node_modules package-lock.json
npm install
```

### 3. Deploy Functions

Deploy all functions:
```bash
firebase deploy --only functions --project creator-ai-proxy
```

Deploy specific function:
```bash
firebase deploy --only functions:routeRequest --project creator-ai-proxy
firebase deploy --only functions:validateLicense --project creator-ai-proxy
```

### 4. Handle "Would you like to proceed with deletion?"

When Firebase detects functions in the cloud that don't exist locally, it will ask:
```
Would you like to proceed with deletion? (y/N)
```

Answer `N` to keep old functions and continue deployment.

## Common Issues and Solutions

### Issue 1: Service Account Does Not Exist

**Error**:
```
Service account creator-ai-proxy@appspot.gserviceaccount.com does not exist
```

**Solution**: Add `serviceAccount` option to function definition (see Service Accounts section above).

### Issue 2: Package Lock Out of Sync

**Error**:
```
npm ci can only install packages when your package.json and package-lock.json are in sync
```

**Solution**:
```bash
nvm use 20
rm -rf node_modules package-lock.json
npm install
```

### Issue 3: ESLint Configuration Not Found

**Error**:
```
ESLint couldn't find a configuration file
```

**Solution**: Ensure `.eslintrc.js` exists in the `functions` directory.

### Issue 4: Functions Show "Skipped (No changes detected)"

If changes aren't being deployed, make a small modification to force redeploy:
```bash
# Edit the function file with a minor change (e.g., update a comment)
firebase deploy --only functions:<function-name>
```

### Issue 5: Divergent Branches on Git Pull

**Error**:
```
fatal: Need to specify how to reconcile divergent branches
```

**Solution**:
```bash
# Option 1: Merge
git pull origin <branch> --no-rebase

# Option 2: Use remote version (discard local)
git fetch origin <branch>
git reset --hard origin/<branch>
```

## Directory Structure

```
/Users/michele/creator-ai-proxy/
├── functions/
│   ├── src/
│   │   ├── api/
│   │   │   ├── ai/
│   │   │   │   └── routeRequest.ts      # Main AI endpoint
│   │   │   ├── auth/
│   │   │   │   └── validateLicense.ts   # License validation
│   │   │   └── plugin-docs/
│   │   │       └── pluginDocs.ts        # Plugin docs cache API
│   │   ├── services/
│   │   │   └── modelService.ts          # AI model service
│   │   ├── lib/
│   │   │   └── secrets.ts               # Secret definitions
│   │   └── index.ts                     # Function exports
│   ├── .eslintrc.js                     # ESLint configuration
│   ├── package.json
│   ├── package-lock.json
│   └── tsconfig.json
└── wordpress-plugin/                    # WordPress plugin code
```

## Useful Commands

```bash
# View function logs
firebase functions:log --project creator-ai-proxy

# View specific function logs
firebase functions:log --only routeRequest --project creator-ai-proxy

# List deployed functions
gcloud functions list --project creator-ai-proxy

# View secret permissions
gcloud secrets get-iam-policy <SECRET_NAME> --project creator-ai-proxy

# Test function locally
cd functions && npm run serve
```

## Contact / Resources

- Firebase Console: https://console.firebase.google.com/project/creator-ai-proxy
- Cloud Console: https://console.cloud.google.com/functions/list?project=creator-ai-proxy
- Secret Manager: https://console.cloud.google.com/security/secret-manager?project=creator-ai-proxy
