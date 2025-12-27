/**
 * @fileoverview Plugin Documentation Cache Cleanup Script
 * @module scripts/cleanupPluginDocsCache
 *
 * @description
 * Cleans up old plugin documentation entries that use full version numbers (X.Y.Z).
 * After the migration to X.Y version matching, old entries with patch versions
 * are no longer needed and can be safely deleted.
 *
 * Usage:
 * Run this script after deploying the X.Y version matching changes.
 * It will:
 * 1. Find all documents with version format X.Y.Z (has 3+ dots)
 * 2. Delete them since they won't be used anymore
 * 3. New documentation will be cached with X.Y format
 *
 * @example
 * npx ts-node src/scripts/cleanupPluginDocsCache.ts
 *
 * Or via Firebase functions shell:
 * firebase functions:shell
 * > cleanupPluginDocsCache()
 */

import * as admin from "firebase-admin";
import { COLLECTIONS } from "../lib/firestore";

// Initialize Firebase Admin if not already initialized
if (!admin.apps.length) {
  admin.initializeApp();
}

const db = admin.firestore();

/**
 * Check if a version string has more than 2 parts (e.g., "3.34.0" has 3 parts)
 *
 * @param version Version string
 * @returns True if version has 3 or more parts
 */
function hasFullVersion(version: string): boolean {
  if (!version) return false;
  const parts = version.split(".");
  return parts.length > 2;
}

/**
 * Normalize version to X.Y format
 *
 * @param version Full version string
 * @returns Normalized X.Y version
 */
function normalizeVersion(version: string): string {
  if (!version || version === "latest") {
    return version || "0.0";
  }
  const parts = version.split(".");
  if (parts.length < 2) {
    return `${parts[0]}.0`;
  }
  return `${parts[0]}.${parts[1]}`;
}

/**
 * Main cleanup function
 *
 * Deletes all plugin docs entries that have full version numbers (X.Y.Z)
 * since they are now superseded by X.Y version matching.
 */
async function cleanupPluginDocsCache(): Promise<void> {
  console.log("Starting plugin docs cache cleanup...");
  console.log("Looking for entries with full version numbers (X.Y.Z)...\n");

  const snapshot = await db.collection(COLLECTIONS.PLUGIN_DOCS_CACHE).get();

  console.log(`Total documents in cache: ${snapshot.size}`);

  const toDelete: Array<{
    docId: string;
    pluginSlug: string;
    version: string;
    normalizedVersion: string;
  }> = [];

  const toKeep: Array<{
    docId: string;
    pluginSlug: string;
    version: string;
  }> = [];

  for (const doc of snapshot.docs) {
    const data = doc.data();
    const version = data.plugin_version || "";
    const pluginSlug = data.plugin_slug || "";

    if (hasFullVersion(version)) {
      toDelete.push({
        docId: doc.id,
        pluginSlug,
        version,
        normalizedVersion: normalizeVersion(version),
      });
    } else {
      toKeep.push({
        docId: doc.id,
        pluginSlug,
        version,
      });
    }
  }

  console.log(`\nDocuments to keep (already X.Y format): ${toKeep.length}`);
  console.log(`Documents to delete (X.Y.Z format): ${toDelete.length}\n`);

  if (toDelete.length === 0) {
    console.log("No documents to delete. Cache is already clean!");
    return;
  }

  console.log("Documents that will be deleted:");
  console.log("================================");
  for (const entry of toDelete) {
    console.log(
      `  ${entry.docId} -> version "${entry.version}" will be replaced by "${entry.normalizedVersion}"`
    );
  }
  console.log("");

  // Delete documents in batches of 500 (Firestore limit)
  const batchSize = 500;
  let deletedCount = 0;

  for (let i = 0; i < toDelete.length; i += batchSize) {
    const batch = db.batch();
    const chunk = toDelete.slice(i, i + batchSize);

    for (const entry of chunk) {
      const docRef = db.collection(COLLECTIONS.PLUGIN_DOCS_CACHE).doc(entry.docId);
      batch.delete(docRef);
    }

    await batch.commit();
    deletedCount += chunk.length;
    console.log(`Deleted ${deletedCount}/${toDelete.length} documents...`);
  }

  console.log(`\nCleanup complete! Deleted ${deletedCount} documents.`);
  console.log(
    "New documentation will be cached with X.Y version format going forward."
  );
}

// Export for use as a Firebase function or direct execution
export { cleanupPluginDocsCache };

// If running directly
if (require.main === module) {
  cleanupPluginDocsCache()
    .then(() => {
      console.log("\nScript finished successfully.");
      process.exit(0);
    })
    .catch((error) => {
      console.error("Error running cleanup:", error);
      process.exit(1);
    });
}
