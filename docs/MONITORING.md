# Creator AI Proxy - Monitoring Setup Guide

## Overview

This guide covers the complete monitoring setup for Creator AI Proxy using Google Cloud Monitoring (formerly Stackdriver).

## Architecture

```
                    ┌─────────────────────────────────────┐
                    │       Google Cloud Monitoring       │
                    │                                     │
   ┌────────────────┼─────────────────────────────────────┼────────────────┐
   │                │                                     │                │
   ▼                ▼                                     ▼                ▼
┌──────────┐  ┌──────────────┐  ┌────────────────┐  ┌──────────────┐
│ Metrics  │  │   Logging    │  │   Alerting     │  │  Dashboards  │
│          │  │              │  │                │  │              │
│ - Latency│  │ - Error logs │  │ - Email        │  │ - Real-time  │
│ - Errors │  │ - Audit logs │  │ - Slack        │  │ - Historical │
│ - Usage  │  │ - Debug logs │  │ - PagerDuty    │  │ - Custom     │
└──────────┘  └──────────────┘  └────────────────┘  └──────────────┘
```

---

## 1. Built-in Metrics

Firebase Cloud Functions automatically emit these metrics:

### Function Execution Metrics

| Metric | Description | Use Case |
|--------|-------------|----------|
| `cloudfunctions.googleapis.com/function/execution_count` | Number of function executions | Traffic volume |
| `cloudfunctions.googleapis.com/function/execution_times` | Execution duration (ms) | Performance |
| `cloudfunctions.googleapis.com/function/user_memory_bytes` | Memory usage | Resource planning |
| `cloudfunctions.googleapis.com/function/active_instances` | Running instances | Scaling |
| `cloudfunctions.googleapis.com/function/network_egress` | Outbound network bytes | Cost tracking |

### Firestore Metrics

| Metric | Description | Use Case |
|--------|-------------|----------|
| `firestore.googleapis.com/document/read_count` | Document reads | Cost tracking |
| `firestore.googleapis.com/document/write_count` | Document writes | Cost tracking |
| `firestore.googleapis.com/document/delete_count` | Document deletes | Activity monitoring |

---

## 2. Custom Metrics

### Setup Custom Metrics via Code

Add to `functions/src/lib/metrics.ts`:

```typescript
import { logger } from 'firebase-functions/v2';

/**
 * Custom metrics for Creator AI Proxy
 */
export const metrics = {
  /**
   * Log AI request metrics
   */
  logAIRequest(data: {
    provider: string;
    taskType: string;
    tokensUsed: number;
    costUsd: number;
    latencyMs: number;
    success: boolean;
  }) {
    logger.info('ai_request_metric', {
      metric_type: 'ai_request',
      ...data,
    });
  },

  /**
   * Log license validation metrics
   */
  logLicenseValidation(data: {
    licenseId: string;
    plan: string;
    success: boolean;
    latencyMs: number;
  }) {
    logger.info('license_validation_metric', {
      metric_type: 'license_validation',
      ...data,
    });
  },

  /**
   * Log job processing metrics
   */
  logJobProcessing(data: {
    jobId: string;
    taskType: string;
    itemCount: number;
    totalTokens: number;
    totalCost: number;
    durationMs: number;
    success: boolean;
  }) {
    logger.info('job_processing_metric', {
      metric_type: 'job_processing',
      ...data,
    });
  },

  /**
   * Log provider health
   */
  logProviderHealth(data: {
    provider: string;
    available: boolean;
    responseTimeMs: number;
    errorMessage?: string;
  }) {
    logger.info('provider_health_metric', {
      metric_type: 'provider_health',
      ...data,
    });
  },
};
```

### Create Log-Based Metrics

Go to: **Cloud Console > Logging > Logs-based Metrics > Create Metric**

**1. AI Request Success Rate**
```
Name: ai_request_success_rate
Filter: resource.type="cloud_function"
         jsonPayload.metric_type="ai_request"
Labels:
  - provider: jsonPayload.provider
  - task_type: jsonPayload.taskType
  - success: jsonPayload.success
```

**2. Provider Latency**
```
Name: provider_latency
Filter: resource.type="cloud_function"
         jsonPayload.metric_type="ai_request"
Type: Distribution
Field: jsonPayload.latencyMs
```

**3. Cost per Request**
```
Name: cost_per_request
Filter: resource.type="cloud_function"
         jsonPayload.metric_type="ai_request"
Type: Distribution
Field: jsonPayload.costUsd
```

---

## 3. Dashboard Setup

### Create Main Dashboard

1. Go to: **Cloud Console > Monitoring > Dashboards**
2. Click **Create Dashboard**
3. Name: "Creator AI Proxy - Main"

### Dashboard Widgets

#### Row 1: Overview

**Widget 1.1: Total Requests (Scorecard)**
```yaml
Metric: cloudfunctions.googleapis.com/function/execution_count
Aggregation: Sum
Period: Last 24 hours
```

**Widget 1.2: Error Rate (Gauge)**
```yaml
Metric: cloudfunctions.googleapis.com/function/execution_count
Filter: status != "ok"
Calculation: (errors / total) * 100
Thresholds:
  - Green: < 1%
  - Yellow: 1-5%
  - Red: > 5%
```

**Widget 1.3: P95 Latency (Scorecard)**
```yaml
Metric: cloudfunctions.googleapis.com/function/execution_times
Aggregation: 95th percentile
Threshold:
  - Green: < 2000ms
  - Red: > 5000ms
```

**Widget 1.4: Active Instances (Scorecard)**
```yaml
Metric: cloudfunctions.googleapis.com/function/active_instances
Aggregation: Max
```

#### Row 2: Traffic & Performance

**Widget 2.1: Request Rate (Line Chart)**
```yaml
Metric: cloudfunctions.googleapis.com/function/execution_count
Group by: function_name
Period: 1 hour
Alignment: Rate
```

**Widget 2.2: Latency Distribution (Heatmap)**
```yaml
Metric: cloudfunctions.googleapis.com/function/execution_times
Group by: function_name
Aggregation: Distribution
```

#### Row 3: Errors & Provider Health

**Widget 3.1: Error Breakdown (Stacked Area)**
```yaml
Metric: cloudfunctions.googleapis.com/function/execution_count
Filter: status != "ok"
Group by: function_name, status
```

**Widget 3.2: Provider Usage (Pie Chart)**
```yaml
Metric: logging/user/ai_request_success_rate
Group by: provider
```

#### Row 4: Resources

**Widget 4.1: Memory Usage (Line Chart)**
```yaml
Metric: cloudfunctions.googleapis.com/function/user_memory_bytes
Group by: function_name
Show limit line: true
```

**Widget 4.2: Instance Count (Line Chart)**
```yaml
Metric: cloudfunctions.googleapis.com/function/active_instances
Group by: function_name
```

### Dashboard JSON Export

Save this configuration for backup:

```json
{
  "displayName": "Creator AI Proxy - Main",
  "mosaicLayout": {
    "columns": 12,
    "tiles": [
      {
        "width": 3,
        "height": 2,
        "widget": {
          "title": "Total Requests (24h)",
          "scorecard": {
            "timeSeriesQuery": {
              "timeSeriesFilter": {
                "filter": "metric.type=\"cloudfunctions.googleapis.com/function/execution_count\" resource.type=\"cloud_function\"",
                "aggregation": {
                  "alignmentPeriod": "86400s",
                  "perSeriesAligner": "ALIGN_SUM"
                }
              }
            }
          }
        }
      }
    ]
  }
}
```

---

## 4. Alerting Policies

### Critical Alerts

#### Alert 1: High Error Rate

```yaml
Name: Creator Proxy - High Error Rate
Condition:
  Metric: cloudfunctions.googleapis.com/function/execution_count
  Filter: status != "ok"
  Aggregation: Count
  Comparison: Threshold
  Threshold: > 5% of total requests
  Duration: 5 minutes

Notification Channels:
  - Email: ops-team@company.com
  - Slack: #creator-alerts

Documentation: |
  ## High Error Rate Detected

  Error rate has exceeded 5% for Creator AI Proxy functions.

  ### Immediate Actions:
  1. Check logs: `gcloud functions logs read --filter "severity>=ERROR" --project creator-ai-proxy`
  2. Check provider status pages
  3. Review recent deployments

  ### Escalation:
  If not resolved in 15 minutes, escalate to on-call engineer.
```

#### Alert 2: Function Timeout

```yaml
Name: Creator Proxy - Function Timeouts
Condition:
  Metric: cloudfunctions.googleapis.com/function/execution_count
  Filter: status = "timeout"
  Threshold: > 10 timeouts in 5 minutes

Notification Channels:
  - Email: ops-team@company.com

Documentation: |
  ## Function Timeouts Detected

  Multiple function executions are timing out.

  ### Possible Causes:
  - Provider API slow/down
  - Large request payloads
  - Firestore contention

  ### Actions:
  1. Check provider status
  2. Review request sizes in logs
  3. Consider increasing timeout in firebase.json
```

#### Alert 3: Memory Exhaustion

```yaml
Name: Creator Proxy - High Memory Usage
Condition:
  Metric: cloudfunctions.googleapis.com/function/user_memory_bytes
  Aggregation: Max
  Threshold: > 80% of memory limit
  Duration: 10 minutes

Notification Channels:
  - Email: ops-team@company.com

Documentation: |
  ## High Memory Usage

  Functions approaching memory limit.

  ### Actions:
  1. Review for memory leaks
  2. Consider increasing memory in firebase.json
  3. Optimize large object handling
```

### Warning Alerts

#### Alert 4: Elevated Latency

```yaml
Name: Creator Proxy - Elevated Latency
Condition:
  Metric: cloudfunctions.googleapis.com/function/execution_times
  Aggregation: 95th percentile
  Threshold: > 5000ms
  Duration: 10 minutes

Severity: Warning
Notification Channels:
  - Email: ops-team@company.com
```

#### Alert 5: Provider Fallback Activated

```yaml
Name: Creator Proxy - Provider Fallback
Condition:
  Log-based metric: provider_fallback_count
  Threshold: > 50 fallbacks in 10 minutes

Severity: Warning
Documentation: |
  Primary provider may be experiencing issues.
  Check provider status page.
```

---

## 5. Log Analysis

### Useful Log Queries

**Find Slow Requests:**
```
resource.type="cloud_function"
resource.labels.function_name="routeRequest"
jsonPayload.latency_ms > 5000
```

**Find Provider Errors:**
```
resource.type="cloud_function"
jsonPayload.provider EXISTS
severity >= ERROR
```

**Track Specific License:**
```
resource.type="cloud_function"
jsonPayload.license_id="LICENSE_KEY_HERE"
```

**Find Rate Limited Requests:**
```
resource.type="cloud_function"
jsonPayload.error_code="RATE_LIMITED"
```

### Log Export to BigQuery

For long-term analysis, export logs to BigQuery:

```bash
# Create BigQuery dataset
bq mk --dataset creator-ai-proxy:function_logs

# Create log sink
gcloud logging sinks create creator-bigquery-sink \
  bigquery.googleapis.com/projects/creator-ai-proxy/datasets/function_logs \
  --log-filter='resource.type="cloud_function" AND resource.labels.project_id="creator-ai-proxy"' \
  --project creator-ai-proxy
```

### Sample BigQuery Queries

**Daily Request Summary:**
```sql
SELECT
  DATE(timestamp) as date,
  resource.labels.function_name as function,
  COUNT(*) as request_count,
  COUNTIF(severity = 'ERROR') as error_count,
  AVG(CAST(JSON_EXTRACT_SCALAR(jsonPayload, '$.latency_ms') AS FLOAT64)) as avg_latency_ms
FROM
  `creator-ai-proxy.function_logs.cloudaudit_googleapis_com_activity_*`
WHERE
  timestamp >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 30 DAY)
GROUP BY
  date, function
ORDER BY
  date DESC, function
```

**Cost by Provider:**
```sql
SELECT
  DATE(timestamp) as date,
  JSON_EXTRACT_SCALAR(jsonPayload, '$.provider') as provider,
  SUM(CAST(JSON_EXTRACT_SCALAR(jsonPayload, '$.cost_usd') AS FLOAT64)) as total_cost,
  SUM(CAST(JSON_EXTRACT_SCALAR(jsonPayload, '$.tokens_used') AS INT64)) as total_tokens
FROM
  `creator-ai-proxy.function_logs.cloudfunctions_*`
WHERE
  JSON_EXTRACT_SCALAR(jsonPayload, '$.metric_type') = 'ai_request'
  AND timestamp >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 7 DAY)
GROUP BY
  date, provider
ORDER BY
  date DESC
```

---

## 6. Uptime Checks

### Setup Health Check Endpoint

Add to `functions/src/api/health.ts`:

```typescript
import { onRequest } from 'firebase-functions/v2/https';

/**
 * Health check endpoint for uptime monitoring
 */
export const healthCheck = onRequest(async (req, res) => {
  const checks = {
    status: 'healthy',
    timestamp: new Date().toISOString(),
    version: process.env.K_REVISION || 'unknown',
    checks: {
      firestore: await checkFirestore(),
      memory: checkMemory(),
    },
  };

  const allHealthy = Object.values(checks.checks).every(c => c.status === 'ok');

  res.status(allHealthy ? 200 : 503).json(checks);
});

async function checkFirestore(): Promise<{ status: string; latencyMs: number }> {
  const start = Date.now();
  try {
    await admin.firestore().collection('_health').doc('ping').set({
      timestamp: admin.firestore.FieldValue.serverTimestamp(),
    });
    return { status: 'ok', latencyMs: Date.now() - start };
  } catch (error) {
    return { status: 'error', latencyMs: Date.now() - start };
  }
}

function checkMemory(): { status: string; usedMb: number; limitMb: number } {
  const used = process.memoryUsage().heapUsed / 1024 / 1024;
  const limit = 2048; // 2GB default
  return {
    status: used < limit * 0.8 ? 'ok' : 'warning',
    usedMb: Math.round(used),
    limitMb: limit,
  };
}
```

### Create Uptime Check

Go to: **Cloud Console > Monitoring > Uptime Checks > Create**

```yaml
Name: Creator Proxy Health Check
Target:
  Protocol: HTTPS
  Resource Type: URL
  Hostname: europe-west1-creator-ai-proxy.cloudfunctions.net
  Path: /api/health

Check Frequency: 1 minute
Timeout: 10 seconds

Response Validation:
  Status Code: 200
  Content Match: "healthy"

Alert Policy:
  Condition: 3 consecutive failures
  Notification: Email + Slack
```

---

## 7. SLO Configuration

### Define Service Level Objectives

**Availability SLO: 99.9%**
```yaml
Name: Creator Proxy Availability
Target: 99.9%
Window: Rolling 30 days
Good Events: Successful requests (status 2xx)
Total Events: All requests
```

**Latency SLO: P95 < 3s**
```yaml
Name: Creator Proxy Latency
Target: 95%
Window: Rolling 7 days
Good Events: Requests with latency < 3000ms
Total Events: All requests
```

### Create SLO in Cloud Console

1. Go to: **Monitoring > Services**
2. Create new service for Creator AI Proxy
3. Add SLOs as defined above
4. Configure error budget alerts

---

## 8. Cost Monitoring

### Setup Budget Alerts

Go to: **Billing > Budgets & Alerts > Create Budget**

```yaml
Name: Creator AI Proxy Monthly Budget
Scope: Project creator-ai-proxy
Budget Amount: $500/month
Alert Thresholds:
  - 50%: Email notification
  - 75%: Email notification
  - 90%: Email + Slack alert
  - 100%: Email + Slack + PagerDuty
```

### Cost Breakdown Report

Create a scheduled query to generate cost reports:

```sql
-- Weekly cost report
SELECT
  service.description as service,
  SUM(cost) as total_cost,
  SUM(usage.amount) as usage_amount,
  usage.unit as usage_unit
FROM
  `creator-ai-proxy.billing_export.gcp_billing_export_v1_*`
WHERE
  _PARTITIONTIME >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 7 DAY)
GROUP BY
  service, usage_unit
ORDER BY
  total_cost DESC
```

---

## Quick Reference

### Monitoring URLs

| Resource | URL |
|----------|-----|
| Dashboards | https://console.cloud.google.com/monitoring/dashboards?project=creator-ai-proxy |
| Alerting | https://console.cloud.google.com/monitoring/alerting?project=creator-ai-proxy |
| Logs Explorer | https://console.cloud.google.com/logs?project=creator-ai-proxy |
| Uptime Checks | https://console.cloud.google.com/monitoring/uptime?project=creator-ai-proxy |
| Error Reporting | https://console.cloud.google.com/errors?project=creator-ai-proxy |

### Useful gcloud Commands

```bash
# List active alerts
gcloud alpha monitoring policies list --project creator-ai-proxy

# Describe specific alert
gcloud alpha monitoring policies describe POLICY_ID --project creator-ai-proxy

# List dashboards
gcloud monitoring dashboards list --project creator-ai-proxy

# View metrics
gcloud monitoring metrics list --filter "metric.type=cloudfunctions" --project creator-ai-proxy
```
