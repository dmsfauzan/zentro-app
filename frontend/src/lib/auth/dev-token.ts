/**
 * Token auth pengembangan: fake token yang cocok dengan
 * FakeFirebaseTokenVerifier backend (base64url JSON claims).
 * Hanya dipakai saat AUTH_DEV_MODE aktif (non-produksi).
 */

export interface DevAccount {
  uid: string;
  name: string;
  email: string;
  label: string;
}

export const DEV_ACCOUNTS: DevAccount[] = [
  {
    uid: "demo-customer",
    name: "Customer Demo",
    email: "customer@zentro.test",
    label: "Customer",
  },
  {
    uid: "demo-seller",
    name: "Seller Demo",
    email: "seller@zentro.test",
    label: "Seller",
  },
  {
    uid: "demo-admin",
    name: "Admin Zentro",
    email: "admin@zentro.test",
    label: "Admin",
  },
  {
    uid: "demo-super-admin",
    name: "Super Admin",
    email: "superadmin@zentro.test",
    label: "Super Admin",
  },
];

function base64Url(value: string): string {
  const bytes = new TextEncoder().encode(value);
  let binary = "";

  for (const byte of bytes) {
    binary += String.fromCharCode(byte);
  }

  return btoa(binary)
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/, "");
}

export function buildDevToken(account: DevAccount): string {
  const claims = {
    uid: account.uid,
    email: account.email,
    name: account.name,
    email_verified: true,
    exp: Math.floor(Date.now() / 1000) + 60 * 60,
  };

  return base64Url(JSON.stringify(claims));
}

export function findDevAccount(uid: string): DevAccount {
  return DEV_ACCOUNTS.find((account) => account.uid === uid) ?? DEV_ACCOUNTS[0];
}
