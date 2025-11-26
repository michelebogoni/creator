# Creator AI Proxy - Operational Runbook

## Quick Reference

| Resource | URL |
|----------|-----|
| Firebase Console | https://console.firebase.google.com/project/creator-ai-proxy |
| Cloud Functions | https://console.cloud.google.com/functions?project=creator-ai-proxy |
| Firestore | https://console.firebase.google.com/project/creator-ai-proxy/firestore |
| Cloud Monitoring | https://console.cloud.google.com/monitoring?project=creator-ai-proxy |
| GitHub Repository | https://github.com/michelebogoni/creator |

---

## Quick Diagnostics

### Check Function Logs

```bash
# View recent logs for all functions
gcloud functions logs read --limit 100 --project creator-ai-proxy

# View logs for specific function
gcloud functions logs read validateLicense --limit 50 --project creator-ai-proxy
gcloud functions logs read routeRequest --limit 50 --project creator-ai-proxy
gcloud functions logs read processJobQueue --limit 50 --project creator-ai-proxy

# Filter by severity
gcloud functions logs read --filter "severity=ERROR" --limit 50 --project creator-ai-proxy
gcloud functions logs read --filter "severity=WARNING" --limit 50 --project creator-ai-proxy

# Filter by time range (last hour)
gcloud functions logs read --filter "timestamp>=\"$(date -u -d '1 hour ago' '+%Y-%m-%dT%H:%M:%SZ')\"" --project creator-ai-proxy
```

### Check Function Status

```bash
# List all deployed functions
gcloud functions list --project creator-ai-proxy

# Get function details
gcloud functions describe validateLicense --project creator-ai-proxy --region europe-west1
gcloud functions describe routeRequest --project creator-ai-proxy --region europe-west1
```

### Check Firestore Usage

```bash
# View database info
gcloud firestore databases describe --project creator-ai-proxy

# Export data for debugging (creates backup)
gcloud firestore export gs://creator-ai-proxy-backups/debug-$(date +%Y%m%d-%H%M%S) --project creator-ai-proxy
```

### Check Provider Status

- **OpenAI**: https://status.openai.com
- **Google Cloud/Gemini**: https://status.cloud.google.com
- **Anthropic Claude**: https://status.anthropic.com

---

## Common Issues & Fixes

### 1. High Error Rate (>5%)

**Symptoms:**
- Increased 4xx/5xx responses
- Alert triggered for error rate threshold

**Diagnostics:**

```bash
# Check recent errors
gcloud functions logs read --filter "severity=ERROR" --limit 100 --project creator-ai-proxy

# Check for specific error patterns
gcloud functions logs read --filter "textPayload:QUOTA_EXCEEDED" --project creator-ai-proxy
gcloud functions logs read --filter "textPayload:RATE_LIMITED" --project creator-ai-proxy
gcloud functions logs read --filter "textPayload:PROVIDER_ERROR" --project creator-ai-proxy
```

**Common Causes & Fixes:**

| Cause | Fix |
|-------|-----|
| Rate Limiting | Wait for reset or increase limits in rate_limit_counters |
| Provider Down | System auto-fallbacks; check provider status pages |
| Quota Exceeded | Check cost_tracking collection; contact customer |
| Invalid Tokens | JWT expired; client needs to re-authenticate |
| Timeout | Increase function timeout in firebase.json |

**Quick Fix - Clear Rate Limits:**

```javascript
// Run in Firebase Console > Firestore
// Navigate to rate_limit_counters collection
// Delete documents for affected IP/license
```

### 2. Slow Performance (Latency > 5s)

**Symptoms:**
- P95 latency exceeds threshold
- Users reporting slow responses

**Diagnostics:**

```bash
# Check function execution times
gcloud monitoring metrics list --project creator-ai-proxy --filter "metric.type=cloudfunctions.googleapis.com/function/execution_times"

# Check Firestore read/write latency
# Go to: Firebase Console > Firestore > Usage > Latency tab
```

**Common Causes & Fixes:**

| Cause | Fix |
|-------|-----|
| Cold Starts | Set minInstances > 0 in firebase.json |
| Large Payloads | Optimize prompt size; use streaming |
| Missing Indexes | Add Firestore composite indexes |
| Provider Slow | Fallback routing will handle; check provider status |

**Quick Fix - Add Firestore Indexes:**

```bash
# Deploy indexes
firebase deploy --only firestore:indexes --project creator-ai-proxy
```

### 3. Function Memory Exceeded

**Symptoms:**
- Function crashes with "Memory limit exceeded"
- Logs show OOM errors

**Fix:**

Edit `firebase.json`:

```json
{
  "functions": [
    {
      "memory": "4GB",
      "timeoutSeconds": 540
    }
  ]
}
```

Then deploy:

```bash
firebase deploy --only functions --project creator-ai-proxy
```

### 4. Provider API Errors

**Symptoms:**
- Specific provider failing consistently
- Fallback providers being used more than expected

**Diagnostics:**

```bash
# Check which provider is failing
gcloud functions logs read --filter "textPayload:provider" --project creator-ai-proxy

# Check specific provider errors
gcloud functions logs read --filter "textPayload:openai AND severity=ERROR" --project creator-ai-proxy
gcloud functions logs read --filter "textPayload:gemini AND severity=ERROR" --project creator-ai-proxy
gcloud functions logs read --filter "textPayload:claude AND severity=ERROR" --project creator-ai-proxy
```

**Provider-Specific Issues:**

| Provider | Common Issues | Fix |
|----------|---------------|-----|
| OpenAI | Rate limit (429), API key invalid | Check key in Secrets Manager; wait for reset |
| Gemini | Quota exceeded, region issues | Verify project billing; check region settings |
| Claude | Rate limit, content filtering | Review prompt; wait for rate limit reset |

**Check API Keys:**

```bash
# List secrets (don't reveal values)
firebase functions:secrets:list --project creator-ai-proxy

# Verify a secret exists
firebase functions:secrets:access OPENAI_API_KEY --project creator-ai-proxy
```

### 5. Jobs Stuck in Processing

**Symptoms:**
- Jobs remain in "processing" status indefinitely
- job_queue documents not updating

**Diagnostics:**

```javascript
// In Firebase Console > Firestore
// Query job_queue collection:
// status == "processing" AND started_at < (now - 15 minutes)
```

**Fix - Manual Job Reset:**

```javascript
// In Firebase Console > Firestore
// For stuck job document:
// Update: status = "failed", error_message = "Job timed out - manual reset"
```

**Fix - Clear Stuck Jobs (Batch):**

```bash
# Use Firebase Admin SDK script
node scripts/clear-stuck-jobs.js
```

### 6. Authentication Failures

**Symptoms:**
- 401/403 errors on protected endpoints
- "Invalid token" errors in logs

**Diagnostics:**

```bash
# Check auth-related errors
gcloud functions logs read --filter "textPayload:JWT" --project creator-ai-proxy
gcloud functions logs read --filter "textPayload:unauthorized" --project creator-ai-proxy
```

**Common Causes:**

| Cause | Fix |
|-------|-----|
| Token Expired | Client needs to call /validate-license again |
| Wrong Site URL | Token was issued for different domain |
| JWT Secret Changed | All existing tokens invalid; clients must re-auth |
| Clock Skew | Server/client time difference > 5 min |

---

## Monitoring Dashboard Setup

### Create Custom Dashboard

1. Go to: **Cloud Console > Monitoring > Dashboards**
2. Click **Create Dashboard**
3. Name: "Creator AI Proxy"
4. Add the following widgets:

### Essential Widgets

**1. Request Rate (Line Chart)**
```
Resource type: cloud_function
Metric: cloudfunctions.googleapis.com/function/execution_count
Group by: function_name
```

**2. Error Rate (Line Chart)**
```
Resource type: cloud_function
Metric: cloudfunctions.googleapis.com/function/execution_count
Filter: status != "ok"
Group by: function_name
```

**3. Latency P95 (Line Chart)**
```
Resource type: cloud_function
Metric: cloudfunctions.googleapis.com/function/execution_times
Aggregation: 95th percentile
Group by: function_name
```

**4. Memory Usage (Line Chart)**
```
Resource type: cloud_function
Metric: cloudfunctions.googleapis.com/function/user_memory_bytes
Group by: function_name
```

**5. Active Instances (Number)**
```
Resource type: cloud_function
Metric: cloudfunctions.googleapis.com/function/active_instances
```

---

## Alerting Policies

### Setup Alerts

Go to: **Cloud Console > Monitoring > Alerting > Create Policy**

### Critical Alerts

**1. High Error Rate**
```yaml
Condition: cloudfunctions.googleapis.com/function/execution_count
Filter: status != "ok"
Threshold: > 10% of total requests
Duration: 5 minutes
Severity: Critical
Notification: Email + Slack
```

**2. Provider Failure**
```yaml
Condition: Custom log-based metric for "PROVIDER_ERROR"
Threshold: > 20 errors in 5 minutes
Severity: Critical
Notification: Email + PagerDuty
```

**3. High Latency**
```yaml
Condition: cloudfunctions.googleapis.com/function/execution_times
Aggregation: 95th percentile
Threshold: > 10000ms
Duration: 10 minutes
Severity: Warning
Notification: Email
```

### Warning Alerts

**4. Memory Usage High**
```yaml
Condition: cloudfunctions.googleapis.com/function/user_memory_bytes
Threshold: > 80% of limit
Duration: 5 minutes
Severity: Warning
Notification: Email
```

**5. Quota Approaching Limit**
```yaml
Condition: Custom metric from cost_tracking
Threshold: tokens_used > 90% of tokens_limit
Severity: Warning
Notification: Email to customer
```

---

## Emergency Procedures

### Production Outage - Severity 1

**Immediate Actions (0-5 minutes):**

1. **Check Status Pages**
   - Firebase: https://status.firebase.google.com
   - GCP: https://status.cloud.google.com
   - OpenAI: https://status.openai.com

2. **Check Function Logs**
   ```bash
   gcloud functions logs read --filter "severity>=ERROR" --limit 50 --project creator-ai-proxy
   ```

3. **Verify Functions Are Deployed**
   ```bash
   gcloud functions list --project creator-ai-proxy
   ```

**Escalation (5-15 minutes):**

4. **If Provider Issue**
   - Fallback routing should handle automatically
   - Monitor fallback provider usage

5. **If Firebase Issue**
   - Check Firebase status page
   - Open support ticket: https://firebase.google.com/support

6. **If Code Issue**
   - Rollback to previous version:
     ```bash
     # Check recent deployments
     gcloud functions describe routeRequest --project creator-ai-proxy --format="value(versionId)"

     # Rollback via GitHub
     git revert HEAD
     git push origin main
     ```

### Database Issues

**Firestore Unavailable:**

1. Check GCP status page
2. All writes are queued by Firebase SDK
3. Reads will fail - implement client-side caching

**Data Corruption:**

1. Stop all writes immediately:
   ```bash
   # Set Firestore rules to deny all writes
   firebase deploy --only firestore:rules --project creator-ai-proxy
   ```

2. Restore from backup:
   ```bash
   gcloud firestore import gs://creator-ai-proxy-backups/[BACKUP_NAME] --project creator-ai-proxy
   ```

### High Costs Emergency

**Unexpected Cost Spike:**

1. **Identify Top Consumers**
   ```javascript
   // Firebase Console > Firestore
   // Query cost_tracking, order by total_cost_usd DESC
   ```

2. **Suspend Abusive Licenses**
   ```javascript
   // Update license document:
   // status = "suspended"
   ```

3. **Implement Emergency Rate Limits**
   ```javascript
   // Reduce rate limits in rateLimit.ts
   // Redeploy
   ```

---

## Maintenance Procedures

### Scheduled Maintenance Window

**Before Maintenance:**

1. Notify users 24 hours in advance
2. Create Firestore backup:
   ```bash
   gcloud firestore export gs://creator-ai-proxy-backups/pre-maintenance-$(date +%Y%m%d) --project creator-ai-proxy
   ```

**During Maintenance:**

1. Monitor deployment:
   ```bash
   firebase deploy --only functions --project creator-ai-proxy
   ```

2. Watch logs for errors:
   ```bash
   gcloud functions logs read --follow --project creator-ai-proxy
   ```

**After Maintenance:**

1. Run smoke tests
2. Verify all endpoints responding
3. Check error rates in monitoring

### Firestore Backup Schedule

**Daily Automated Backups:**

Create Cloud Scheduler job:
```bash
gcloud scheduler jobs create http firestore-daily-backup \
  --schedule="0 2 * * *" \
  --uri="https://firestore.googleapis.com/v1/projects/creator-ai-proxy/databases/(default):exportDocuments" \
  --http-method=POST \
  --oauth-service-account-email=firebase-adminsdk@creator-ai-proxy.iam.gserviceaccount.com \
  --message-body='{"outputUriPrefix":"gs://creator-ai-proxy-backups/daily"}' \
  --project creator-ai-proxy
```

### Log Retention

Logs are retained for 30 days by default. For compliance, export to BigQuery:

```bash
gcloud logging sinks create creator-logs-sink \
  bigquery.googleapis.com/projects/creator-ai-proxy/datasets/function_logs \
  --log-filter='resource.type="cloud_function"' \
  --project creator-ai-proxy
```

---

## Useful Scripts

### Check License Status

```bash
# Using Firebase CLI
firebase firestore:get licenses/[LICENSE_KEY] --project creator-ai-proxy
```

### Reset User Quota

```javascript
// Run in Firebase Console
const licenseRef = db.collection('licenses').doc('LICENSE_KEY');
await licenseRef.update({
  tokens_used: 0,
  updated_at: admin.firestore.FieldValue.serverTimestamp()
});
```

### Force Job Completion

```javascript
// Run in Firebase Console
const jobRef = db.collection('job_queue').doc('JOB_ID');
await jobRef.update({
  status: 'completed',
  completed_at: admin.firestore.FieldValue.serverTimestamp(),
  result: { manual_completion: true }
});
```

---

## Contacts & Escalation

| Role | Contact | When to Contact |
|------|---------|-----------------|
| On-Call Engineer | [Your contact] | Any P1/P2 incident |
| Firebase Support | https://firebase.google.com/support | Platform issues |
| GCP Support | https://cloud.google.com/support | Infrastructure issues |
| OpenAI Support | https://help.openai.com | API issues |
| Anthropic Support | https://support.anthropic.com | Claude API issues |

---

## Appendix: Error Codes Reference

| Code | HTTP Status | Description | User Action |
|------|-------------|-------------|-------------|
| `LICENSE_NOT_FOUND` | 404 | License key doesn't exist | Check license key |
| `LICENSE_EXPIRED` | 403 | License has expired | Renew license |
| `LICENSE_SUSPENDED` | 403 | License manually suspended | Contact support |
| `URL_MISMATCH` | 403 | Site URL doesn't match | Use registered URL |
| `QUOTA_EXCEEDED` | 429 | Monthly tokens exhausted | Upgrade plan or wait |
| `RATE_LIMITED` | 429 | Too many requests | Wait and retry |
| `INVALID_TOKEN` | 401 | JWT invalid or expired | Re-authenticate |
| `PROVIDER_ERROR` | 503 | AI provider failure | Auto-retry with fallback |
| `INVALID_TASK_TYPE` | 400 | Unknown task type | Check API docs |
| `JOB_NOT_FOUND` | 404 | Job ID doesn't exist | Check job ID |
