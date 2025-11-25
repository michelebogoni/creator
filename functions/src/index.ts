import {onRequest} from "firebase-functions/https";
import * as logger from "firebase-functions/logger";

export const helloWorld = onRequest((req, res) => {
  logger.info("Hello logs from Creator AI Proxy!");
  res.send("Hello from Creator AI Proxy!");
});
