/**
 * Konfigurasi publik frontend ZENTRO.
 *
 * Hanya nilai NON-rahasia yang boleh berada di sini (prefix NEXT_PUBLIC_).
 * Kredensial Firebase service account TIDAK PERNAH ada di frontend.
 */

export const APP_NAME = "ZENTRO";
export const APP_TAGLINE = "Everything in One Place";

export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";

export const FIREBASE_CONFIG = {
  apiKey: process.env.NEXT_PUBLIC_FIREBASE_API_KEY ?? "",
  authDomain: process.env.NEXT_PUBLIC_FIREBASE_AUTH_DOMAIN ?? "",
  projectId: process.env.NEXT_PUBLIC_FIREBASE_PROJECT_ID ?? "",
  appId: process.env.NEXT_PUBLIC_FIREBASE_APP_ID ?? "",
} as const;

export const isFirebaseConfigured = Boolean(
  FIREBASE_CONFIG.apiKey && FIREBASE_CONFIG.projectId,
);

const devModeFlag = process.env.NEXT_PUBLIC_AUTH_DEV_MODE;

/**
 * Mode auth pengembangan: memakai fake token yang cocok dengan
 * FakeFirebaseTokenVerifier backend (lokal/E2E). Tidak pernah aktif di produksi.
 */
export const AUTH_DEV_MODE =
  process.env.NODE_ENV !== "production" &&
  (devModeFlag === "true" || !isFirebaseConfigured);

export const CURRENCY = "IDR";
