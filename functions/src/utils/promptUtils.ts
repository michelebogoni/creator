/**
 * @fileoverview Prompt Utility Functions
 * @module utils/promptUtils
 *
 * @description
 * Utility functions for prompt validation and sanitization.
 */

/**
 * Sanitizes a prompt by removing potentially dangerous content
 *
 * @param {string} prompt - The prompt to sanitize
 * @returns {string} Sanitized prompt
 */
export function sanitizePrompt(prompt: string): string {
  // Remove script tags and their content
  let sanitized = prompt.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "");

  // Remove other potentially dangerous tags
  sanitized = sanitized.replace(/<(iframe|object|embed|form)[^>]*>.*?<\/\1>/gi, "");

  // Remove event handlers
  sanitized = sanitized.replace(/\son\w+\s*=/gi, " data-removed=");

  return sanitized.trim();
}

/**
 * Validates a prompt for length and content
 *
 * @param {string} prompt - The prompt to validate
 * @param {number} maxLength - Maximum allowed length (default 10000)
 * @returns {{ valid: boolean; error?: string }} Validation result
 */
export function validatePrompt(
  prompt: string,
  maxLength: number = 10000
): { valid: boolean; error?: string } {
  if (!prompt || typeof prompt !== "string") {
    return { valid: false, error: "Prompt is required and must be a string" };
  }

  const trimmed = prompt.trim();

  if (trimmed.length === 0) {
    return { valid: false, error: "Prompt cannot be empty" };
  }

  if (trimmed.length > maxLength) {
    return {
      valid: false,
      error: `Prompt exceeds maximum length of ${maxLength} characters`,
    };
  }

  return { valid: true };
}
