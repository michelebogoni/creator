/**
 * @fileoverview Creator AI Proxy - Firebase Cloud Functions Entry Point
 * @module index
 *
 * @description
 * This is the main entry point for all Cloud Functions.
 * All functions are exported from this file and deployed to Firebase.
 *
 * @version 1.0.0
 * @author Creator AI Team
 */

// ==================== AUTH ENDPOINTS ====================

/**
 * POST /api/auth/validate-license
 *
 * Validates a license key and returns JWT token for authenticated access.
 *
 * @see {@link module:api/auth/validateLicense}
 */
export { validateLicense } from "./api/auth/validateLicense";

// ==================== AI ENDPOINTS (Milestone 4) ====================
// export { routeRequest } from "./api/ai/routeRequest";

// ==================== TASK ENDPOINTS (Milestone 5) ====================
// export { submitTask } from "./api/tasks/submitTask";
// export { getTaskStatus } from "./api/tasks/getStatus";
