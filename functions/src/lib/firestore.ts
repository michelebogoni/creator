/**
 * @fileoverview Firestore database helpers for Creator AI Proxy
 * @module lib/firestore
 */

import * as admin from "firebase-admin";
import { Timestamp, FieldValue } from "firebase-admin/firestore";
import { License, UpdateLicenseData } from "../types/License";
import { AuditLogEntry, RateLimitCounter } from "../types/APIResponse";
import {
  Job,
  CreateJobData,
  UpdateJobData,
  JobStatus,
  JobProgress,
} from "../types/Job";

// Initialize Firebase Admin if not already initialized
if (!admin.apps.length) {
  admin.initializeApp();
}

/**
 * Firestore database instance
 */
export const db = admin.firestore();

/**
 * Collection names as constants
 */
export const COLLECTIONS = {
  LICENSES: "licenses",
  AUDIT_LOGS: "audit_logs",
  RATE_LIMIT_COUNTERS: "rate_limit_counters",
  JOB_QUEUE: "job_queue",
  COST_TRACKING: "cost_tracking",
} as const;

// ==================== LICENSE OPERATIONS ====================

/**
 * Retrieves a license by its license key
 *
 * @param {string} licenseKey - The license key to search for
 * @returns {Promise<License | null>} The license document or null if not found
 *
 * @example
 * ```typescript
 * const license = await getLicenseByKey("CREATOR-2024-ABCDE-FGHIJ");
 * if (license) {
 *   console.log(license.plan);
 * }
 * ```
 */
export async function getLicenseByKey(
  licenseKey: string
): Promise<License | null> {
  const docRef = db.collection(COLLECTIONS.LICENSES).doc(licenseKey);
  const doc = await docRef.get();

  if (!doc.exists) {
    return null;
  }

  return doc.data() as License;
}

/**
 * Updates a license document
 *
 * @param {string} licenseKey - The license key to update
 * @param {UpdateLicenseData} data - The data to update
 * @returns {Promise<void>}
 *
 * @example
 * ```typescript
 * await updateLicense("CREATOR-2024-ABCDE-FGHIJ", {
 *   site_token: "new_jwt_token",
 *   updated_at: Timestamp.now()
 * });
 * ```
 */
export async function updateLicense(
  licenseKey: string,
  data: UpdateLicenseData
): Promise<void> {
  const docRef = db.collection(COLLECTIONS.LICENSES).doc(licenseKey);
  await docRef.update({
    ...data,
    updated_at: Timestamp.now(),
  });
}

/**
 * Increments the tokens_used counter for a license
 *
 * @param {string} licenseKey - The license key
 * @param {number} tokensToAdd - Number of tokens to add
 * @returns {Promise<void>}
 */
export async function incrementTokensUsed(
  licenseKey: string,
  tokensToAdd: number
): Promise<void> {
  const docRef = db.collection(COLLECTIONS.LICENSES).doc(licenseKey);
  await docRef.update({
    tokens_used: FieldValue.increment(tokensToAdd),
    updated_at: Timestamp.now(),
  });
}

// ==================== AUDIT LOG OPERATIONS ====================

/**
 * Creates an audit log entry
 *
 * @param {Omit<AuditLogEntry, "created_at">} entry - The audit log data
 * @returns {Promise<string>} The created document ID
 *
 * @example
 * ```typescript
 * await createAuditLog({
 *   license_id: "CREATOR-2024-ABCDE-FGHIJ",
 *   request_type: "license_validation",
 *   status: "success",
 *   ip_address: "192.168.1.1"
 * });
 * ```
 */
export async function createAuditLog(
  entry: Omit<AuditLogEntry, "created_at">
): Promise<string> {
  const docRef = await db.collection(COLLECTIONS.AUDIT_LOGS).add({
    ...entry,
    created_at: Timestamp.now(),
  });
  return docRef.id;
}

// ==================== RATE LIMIT OPERATIONS ====================

/**
 * Gets the current rate limit counter for an endpoint/IP combination
 *
 * @param {string} endpoint - The endpoint being rate limited
 * @param {string} ipAddress - The client IP address
 * @returns {Promise<number>} Current request count in the window
 *
 * @example
 * ```typescript
 * const count = await getRateLimitCount("validate_license", "192.168.1.1");
 * if (count >= 10) {
 *   // Rate limited
 * }
 * ```
 */
export async function getRateLimitCount(
  endpoint: string,
  ipAddress: string
): Promise<number> {
  const now = new Date();
  const currentMinute = Math.floor(now.getTime() / 60000); // Minute bucket
  const docId = `${endpoint}:${ipAddress}:${currentMinute}`;

  const docRef = db.collection(COLLECTIONS.RATE_LIMIT_COUNTERS).doc(docId);
  const doc = await docRef.get();

  if (!doc.exists) {
    return 0;
  }

  const data = doc.data() as RateLimitCounter;
  return data.count;
}

/**
 * Increments the rate limit counter for an endpoint/IP combination
 *
 * @param {string} endpoint - The endpoint being rate limited
 * @param {string} ipAddress - The client IP address
 * @returns {Promise<number>} New request count after increment
 *
 * @example
 * ```typescript
 * const newCount = await incrementRateLimitCounter("validate_license", "192.168.1.1");
 * ```
 */
export async function incrementRateLimitCounter(
  endpoint: string,
  ipAddress: string
): Promise<number> {
  const now = new Date();
  const currentMinute = Math.floor(now.getTime() / 60000);
  const docId = `${endpoint}:${ipAddress}:${currentMinute}`;

  // TTL: 2 minutes from now (cleanup buffer)
  const ttl = Timestamp.fromMillis(now.getTime() + 120000);

  const docRef = db.collection(COLLECTIONS.RATE_LIMIT_COUNTERS).doc(docId);

  // Use transaction for atomic increment
  const result = await db.runTransaction(async (transaction) => {
    const doc = await transaction.get(docRef);

    if (!doc.exists) {
      const newData: RateLimitCounter = {
        endpoint,
        ip_address: ipAddress,
        hour: currentMinute,
        count: 1,
        ttl,
      };
      transaction.set(docRef, newData);
      return 1;
    }

    const currentCount = (doc.data() as RateLimitCounter).count;
    const newCount = currentCount + 1;
    transaction.update(docRef, { count: newCount });
    return newCount;
  });

  return result;
}

/**
 * Checks if an IP is rate limited and increments counter atomically
 *
 * @param {string} endpoint - The endpoint being rate limited
 * @param {string} ipAddress - The client IP address
 * @param {number} limit - Maximum requests per minute (default: 10)
 * @returns {Promise<{ limited: boolean; count: number }>} Rate limit status
 *
 * @example
 * ```typescript
 * const { limited, count } = await checkAndIncrementRateLimit("validate_license", "192.168.1.1");
 * if (limited) {
 *   return res.status(429).send("Too many requests");
 * }
 * ```
 */
export async function checkAndIncrementRateLimit(
  endpoint: string,
  ipAddress: string,
  limit: number = 10
): Promise<{ limited: boolean; count: number }> {
  const currentCount = await getRateLimitCount(endpoint, ipAddress);

  if (currentCount >= limit) {
    return { limited: true, count: currentCount };
  }

  const newCount = await incrementRateLimitCounter(endpoint, ipAddress);
  return { limited: newCount > limit, count: newCount };
}

// ==================== COST TRACKING OPERATIONS ====================

/**
 * Cost tracking document structure
 */
export interface CostTrackingDocument {
  license_id: string;
  month: string;
  openai_tokens_input: number;
  openai_tokens_output: number;
  openai_cost_usd: number;
  gemini_tokens_input: number;
  gemini_tokens_output: number;
  gemini_cost_usd: number;
  claude_tokens_input: number;
  claude_tokens_output: number;
  claude_cost_usd: number;
  total_cost_usd: number;
}

/**
 * Updates cost tracking for a license
 *
 * @param {string} licenseId - The license ID
 * @param {string} provider - The provider used (openai, gemini, claude)
 * @param {number} tokensInput - Input tokens consumed
 * @param {number} tokensOutput - Output tokens generated
 * @param {number} costUsd - Cost in USD
 * @returns {Promise<void>}
 *
 * @example
 * ```typescript
 * await updateCostTracking(
 *   "CREATOR-2024-ABCDE-FGHIJ",
 *   "openai",
 *   1000,
 *   500,
 *   0.0125
 * );
 * ```
 */
export async function updateCostTracking(
  licenseId: string,
  provider: "openai" | "gemini" | "claude",
  tokensInput: number,
  tokensOutput: number,
  costUsd: number
): Promise<void> {
  const now = new Date();
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}`;
  const docId = `${licenseId}_${month}`;

  const docRef = db.collection(COLLECTIONS.COST_TRACKING).doc(docId);

  await db.runTransaction(async (transaction) => {
    const doc = await transaction.get(docRef);

    if (!doc.exists) {
      // Create new document
      const newData: CostTrackingDocument = {
        license_id: licenseId,
        month,
        openai_tokens_input: 0,
        openai_tokens_output: 0,
        openai_cost_usd: 0,
        gemini_tokens_input: 0,
        gemini_tokens_output: 0,
        gemini_cost_usd: 0,
        claude_tokens_input: 0,
        claude_tokens_output: 0,
        claude_cost_usd: 0,
        total_cost_usd: 0,
      };

      // Update the specific provider fields
      newData[`${provider}_tokens_input` as keyof CostTrackingDocument] = tokensInput as never;
      newData[`${provider}_tokens_output` as keyof CostTrackingDocument] = tokensOutput as never;
      newData[`${provider}_cost_usd` as keyof CostTrackingDocument] = costUsd as never;
      newData.total_cost_usd = costUsd;

      transaction.set(docRef, newData);
    } else {
      // Update existing document
      const updateData: { [key: string]: FirebaseFirestore.FieldValue } = {};
      updateData[`${provider}_tokens_input`] = FieldValue.increment(tokensInput);
      updateData[`${provider}_tokens_output`] = FieldValue.increment(tokensOutput);
      updateData[`${provider}_cost_usd`] = FieldValue.increment(costUsd);
      updateData.total_cost_usd = FieldValue.increment(costUsd);

      transaction.update(docRef, updateData);
    }
  });
}

/**
 * Gets the license by site_token
 *
 * @param {string} siteToken - The JWT site token
 * @returns {Promise<License | null>} The license or null
 */
export async function getLicenseBySiteToken(
  siteToken: string
): Promise<import("../types/License").License | null> {
  const snapshot = await db
    .collection(COLLECTIONS.LICENSES)
    .where("site_token", "==", siteToken)
    .limit(1)
    .get();

  if (snapshot.empty) {
    return null;
  }

  return snapshot.docs[0].data() as import("../types/License").License;
}

// ==================== UTILITY FUNCTIONS ====================

/**
 * Converts a Firestore Timestamp to ISO string
 *
 * @param {Timestamp} timestamp - The Firestore timestamp
 * @returns {string} ISO date string
 */
export function timestampToISO(timestamp: Timestamp): string {
  return timestamp.toDate().toISOString();
}

/**
 * Converts a Date to Firestore Timestamp
 *
 * @param {Date} date - The JavaScript Date
 * @returns {Timestamp} Firestore Timestamp
 */
export function dateToTimestamp(date: Date): Timestamp {
  return Timestamp.fromDate(date);
}

/**
 * Checks if a Firestore Timestamp is in the past
 *
 * @param {Timestamp} timestamp - The timestamp to check
 * @returns {boolean} True if the timestamp is in the past
 */
export function isTimestampExpired(timestamp: Timestamp): boolean {
  return timestamp.toMillis() < Date.now();
}

// ==================== JOB QUEUE OPERATIONS ====================

/**
 * Generates a unique job ID
 *
 * @returns {string} Job ID in format job_uuid
 */
export function generateJobId(): string {
  const uuid = crypto.randomUUID();
  return `job_${uuid}`;
}

/**
 * Creates a new job in the job_queue collection
 *
 * @param {CreateJobData} data - Job creation data
 * @returns {Promise<Job>} The created job with all fields
 *
 * @example
 * ```typescript
 * const job = await createJob({
 *   license_id: "CREATOR-2024-ABCDE-FGHIJ",
 *   task_type: "bulk_articles",
 *   task_data: { topics: ["SEO", "WordPress"], tone: "professional" }
 * });
 * console.log(job.job_id); // job_f47ac10b-58cc-...
 * ```
 */
export async function createJob(data: CreateJobData): Promise<Job> {
  const jobId = generateJobId();
  const now = Timestamp.now();

  const job: Job = {
    job_id: jobId,
    license_id: data.license_id,
    task_type: data.task_type,
    task_data: data.task_data,
    status: "pending",
    attempts: 0,
    max_attempts: 3,
    created_at: now,
  };

  await db.collection(COLLECTIONS.JOB_QUEUE).doc(jobId).set(job);

  return job;
}

/**
 * Retrieves a job by its ID
 *
 * @param {string} jobId - The job ID
 * @returns {Promise<Job | null>} The job or null if not found
 *
 * @example
 * ```typescript
 * const job = await getJobById("job_f47ac10b-58cc-...");
 * if (job) {
 *   console.log(job.status);
 * }
 * ```
 */
export async function getJobById(jobId: string): Promise<Job | null> {
  const docRef = db.collection(COLLECTIONS.JOB_QUEUE).doc(jobId);
  const doc = await docRef.get();

  if (!doc.exists) {
    return null;
  }

  return doc.data() as Job;
}

/**
 * Updates a job document
 *
 * @param {string} jobId - The job ID to update
 * @param {UpdateJobData} data - The data to update
 * @returns {Promise<void>}
 *
 * @example
 * ```typescript
 * await updateJob("job_f47ac10b-58cc-...", {
 *   status: "processing",
 *   started_at: Timestamp.now()
 * });
 * ```
 */
export async function updateJob(
  jobId: string,
  data: UpdateJobData
): Promise<void> {
  const docRef = db.collection(COLLECTIONS.JOB_QUEUE).doc(jobId);
  await docRef.update(data as FirebaseFirestore.UpdateData<Job>);
}

/**
 * Updates job status
 *
 * @param {string} jobId - The job ID
 * @param {JobStatus} status - New status
 * @returns {Promise<void>}
 */
export async function updateJobStatus(
  jobId: string,
  status: JobStatus
): Promise<void> {
  const updateData: UpdateJobData = { status };

  if (status === "processing") {
    updateData.started_at = Timestamp.now();
  } else if (status === "completed" || status === "failed") {
    updateData.completed_at = Timestamp.now();
  }

  await updateJob(jobId, updateData);
}

/**
 * Updates job progress
 *
 * @param {string} jobId - The job ID
 * @param {JobProgress} progress - Progress data
 * @returns {Promise<void>}
 *
 * @example
 * ```typescript
 * await updateJobProgress("job_f47ac10b-58cc-...", {
 *   progress_percent: 30,
 *   items_completed: 3,
 *   items_total: 10,
 *   current_item_index: 3,
 *   current_item_title: "Generating article: WordPress Security",
 *   eta_seconds: 210
 * });
 * ```
 */
export async function updateJobProgress(
  jobId: string,
  progress: JobProgress
): Promise<void> {
  await updateJob(jobId, { progress });
}

/**
 * Marks a job as completed with result
 *
 * @param {string} jobId - The job ID
 * @param {Record<string, unknown>} result - Job result
 * @param {number} tokensUsed - Total tokens used
 * @param {number} costUsd - Total cost in USD
 * @returns {Promise<void>}
 */
export async function completeJob(
  jobId: string,
  result: Record<string, unknown>,
  tokensUsed: number,
  costUsd: number
): Promise<void> {
  await updateJob(jobId, {
    status: "completed",
    result: result as unknown as import("../types/Job").JobResult,
    tokens_used: tokensUsed,
    cost_usd: costUsd,
    completed_at: Timestamp.now(),
    progress: {
      progress_percent: 100,
      items_completed: 0, // Will be set by caller
      items_total: 0,
      current_item_index: 0,
    },
  });
}

/**
 * Marks a job as failed
 *
 * @param {string} jobId - The job ID
 * @param {string} errorMessage - Error description
 * @returns {Promise<void>}
 */
export async function failJob(
  jobId: string,
  errorMessage: string
): Promise<void> {
  await updateJob(jobId, {
    status: "failed",
    error_message: errorMessage,
    completed_at: Timestamp.now(),
  });
}

/**
 * Increments job attempts counter
 *
 * @param {string} jobId - The job ID
 * @returns {Promise<number>} New attempt count
 */
export async function incrementJobAttempts(jobId: string): Promise<number> {
  const docRef = db.collection(COLLECTIONS.JOB_QUEUE).doc(jobId);

  const result = await db.runTransaction(async (transaction) => {
    const doc = await transaction.get(docRef);
    if (!doc.exists) {
      throw new Error("Job not found");
    }

    const currentAttempts = (doc.data() as Job).attempts;
    const newAttempts = currentAttempts + 1;

    transaction.update(docRef, { attempts: newAttempts });
    return newAttempts;
  });

  return result;
}

/**
 * Gets all jobs for a license
 *
 * @param {string} licenseId - The license ID
 * @param {number} limit - Maximum number of jobs to return
 * @returns {Promise<Job[]>} Array of jobs
 */
export async function getJobsByLicense(
  licenseId: string,
  limit: number = 10
): Promise<Job[]> {
  const snapshot = await db
    .collection(COLLECTIONS.JOB_QUEUE)
    .where("license_id", "==", licenseId)
    .orderBy("created_at", "desc")
    .limit(limit)
    .get();

  return snapshot.docs.map((doc) => doc.data() as Job);
}

/**
 * Checks if a license has pending jobs (to prevent queue flooding)
 *
 * @param {string} licenseId - The license ID
 * @param {number} maxPending - Maximum allowed pending jobs
 * @returns {Promise<{ allowed: boolean; pendingCount: number }>}
 */
export async function checkPendingJobsLimit(
  licenseId: string,
  maxPending: number = 5
): Promise<{ allowed: boolean; pendingCount: number }> {
  const snapshot = await db
    .collection(COLLECTIONS.JOB_QUEUE)
    .where("license_id", "==", licenseId)
    .where("status", "in", ["pending", "processing"])
    .get();

  const pendingCount = snapshot.size;
  return {
    allowed: pendingCount < maxPending,
    pendingCount,
  };
}
