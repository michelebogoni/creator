# Creator - Deployment Guide

## Overview

This monorepo contains two independently deployable components:

| Component | Location | Deployment Target | Trigger |
|-----------|----------|-------------------|---------|
| Firebase Functions | `functions/` | Google Cloud | Push to main (functions/**) |
| WordPress Plugin | `packages/creator-core-plugin/` | GitHub Releases | Git tag (plugin-v*) |

---

## Firebase Functions Deployment

### Automatic Deployment (CI/CD)

The workflow `.github/workflows/firebase-deploy.yml` automatically deploys when:
- Changes are pushed to `main` branch
- Modified files are in `functions/`, `firebase.json`, or `.firebaserc`

### Manual Deployment

```bash
# Install dependencies
cd functions
npm ci

# Build TypeScript
npm run build

# Deploy to Firebase
firebase deploy --only functions --project creator-ai-proxy
```

### Required Secrets (GitHub)

| Secret | Description |
|--------|-------------|
| `FIREBASE_SERVICE_ACCOUNT` | Service account JSON key |

### Required GCP Permissions

The service account needs these roles:
- `roles/cloudfunctions.admin`
- `roles/secretmanager.secretAccessor`
- `roles/secretmanager.viewer`
- `roles/firebaseextensions.viewer`
- `roles/iam.serviceAccountUser`
- `roles/resourcemanager.projectIamAdmin`

### Required Secrets (Secret Manager)

| Secret | Description |
|--------|-------------|
| `JWT_SECRET` | Secret for JWT token signing |
| `CLAUDE_API_KEY` | Anthropic API key |
| `OPENAI_API_KEY` | OpenAI API key |
| `GEMINI_API_KEY` | Google Gemini API key |

---

## WordPress Plugin Deployment

### Automatic Release (CI/CD)

The workflow `.github/workflows/deploy-plugin.yml` creates a release when:
- A tag matching `plugin-v*` is pushed (e.g., `plugin-v1.0.0`)

### Creating a Release

```bash
# Ensure all changes are committed
git add .
git commit -m "Release plugin v1.0.0"

# Create and push tag
git tag plugin-v1.0.0
git push origin plugin-v1.0.0
```

This will:
1. Build the plugin (production dependencies only)
2. Create a ZIP file
3. Create a GitHub Release with the ZIP attached

### Manual Build

```bash
# Install production dependencies
cd packages/creator-core-plugin/creator-core
composer install --no-dev --optimize-autoloader

# Create ZIP (from repo root)
cd ../../..
mkdir -p build
cp -r packages/creator-core-plugin/creator-core build/creator-core
zip -r build/creator-core.zip build/creator-core
```

### Installing the Plugin

1. Download `creator-core.zip` from GitHub Releases
2. WordPress Admin > Plugins > Add New > Upload Plugin
3. Select the ZIP file and click "Install Now"
4. Click "Activate"

---

## Environment Configuration

### Firebase Functions

Environment variables are managed via Secret Manager. To add/update:

```bash
# Add a new secret
echo -n "your-secret-value" | gcloud secrets create SECRET_NAME \
  --project=creator-ai-proxy --data-file=-

# Update existing secret
echo -n "new-value" | gcloud secrets versions add SECRET_NAME \
  --project=creator-ai-proxy --data-file=-
```

### WordPress Plugin

Configuration is stored in WordPress options:

| Option | Description |
|--------|-------------|
| `creator_license_key` | License key for API access |
| `creator_proxy_url` | Firebase proxy URL |
| `creator_settings` | Plugin settings array |

---

## Troubleshooting

### Firebase Deployment Fails

**Error: Permission denied for Extensions API**
```bash
gcloud projects add-iam-policy-binding creator-ai-proxy \
  --member="serviceAccount:YOUR_SERVICE_ACCOUNT" \
  --role="roles/firebaseextensions.viewer"
```

**Error: Secret Manager access denied**
```bash
gcloud projects add-iam-policy-binding creator-ai-proxy \
  --member="serviceAccount:YOUR_SERVICE_ACCOUNT" \
  --role="roles/secretmanager.secretAccessor"

gcloud projects add-iam-policy-binding creator-ai-proxy \
  --member="serviceAccount:YOUR_SERVICE_ACCOUNT" \
  --role="roles/secretmanager.viewer"
```

### WordPress Plugin Issues

**Plugin shows "Creator Core requires PHP 7.4+"**
- Upgrade your PHP version to 7.4 or higher

**Connection to proxy fails**
- Check `creator_proxy_url` option is correct
- Verify license key is valid
- Check Firebase Functions are deployed and healthy

---

## Monitoring

### Firebase Functions

- **Logs:** Firebase Console > Functions > Logs
- **Metrics:** Google Cloud Console > Cloud Functions > Metrics
- **Errors:** Google Cloud Console > Error Reporting

### WordPress Plugin

- **Audit Log:** WordPress Admin > Creator > Audit Log
- **Debug Log:** Enable `WP_DEBUG_LOG` in `wp-config.php`
