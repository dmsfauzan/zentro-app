import { FIREBASE_CONFIG, isFirebaseConfigured } from "@/lib/config";

/**
 * Inisialisasi Firebase Auth di klien (public keys saja).
 * Modul firebase diimpor dinamis agar tidak membebani bundle saat dev mode.
 */
export async function getFirebaseAuth() {
  if (!isFirebaseConfigured) return null;

  const { getApps, initializeApp } = await import("firebase/app");
  const { getAuth } = await import("firebase/auth");

  const app = getApps()[0] ?? initializeApp(FIREBASE_CONFIG);

  return getAuth(app);
}
