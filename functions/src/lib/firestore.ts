/**
 * @fileoverview Firestore database helpers for Creator AI Proxy
 * @module lib/firestore
 */

import * as admin from "firebase-admin";
import { Timestamp, FieldValue } from "firebase-admin/firestore";
import { License, UpdateLicenseData } from "../types/License";
import { AuditLogEntry, RateLimitCounter } from "../types/APIResponse";

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
