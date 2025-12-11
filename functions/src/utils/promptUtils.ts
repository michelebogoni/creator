/**
 * @fileoverview Prompt validation and sanitization utilities
 * @module utils/promptUtils
 */

/**
 * Result of prompt validation
 */
export interface PromptValidationResult {
  valid: boolean;
  error?: string;
}

/**
 * Validates a prompt string
 *
 * @param {string} prompt - The prompt to validate
 * @param {number} maxLength - Maximum allowed length
 * @returns {PromptValidationResult} Validation result
 */
export function validatePrompt(
  prompt: string,
  maxLength: number
): PromptValidationResult {
  if (!prompt || typeof prompt !== "string") {
    return {
      valid: false,
      error: "Prompt is required and must be a string",
    };
  }

  const trimmedPrompt = prompt.trim();

  if (trimmedPrompt.length === 0) {
    return {
      valid: false,
      error: "Prompt cannot be empty",
    };
  }

  if (trimmedPrompt.length > maxLength) {
    return {
      valid: false,
      error: `Prompt exceeds maximum length of ${maxLength} characters`,
    };
  }

  return { valid: true };
}

/**
 * Sanitizes a prompt string
 *
 * @param {string} prompt - The prompt to sanitize
 * @returns {string} Sanitized prompt
 */
export function sanitizePrompt(prompt: string): string {
  if (!prompt || typeof prompt !== "string") {
    return "";
  }

  // Trim whitespace
  let sanitized = prompt.trim();

  // Remove null bytes
  sanitized = sanitized.replace(/\0/g, "");

  // Normalize line endings
  sanitized = sanitized.replace(/\r\n/g, "\n").replace(/\r/g, "\n");

  // Remove excessive whitespace (more than 2 consecutive newlines)
  sanitized = sanitized.replace(/\n{3,}/g, "\n\n");

  return sanitized;
}
