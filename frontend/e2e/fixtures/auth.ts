import { expect, type Page } from "@playwright/test";

export type DemoRole = "customer" | "seller" | "admin";

const accounts: Record<DemoRole, { uid: string; email: string; name: string }> = {
  customer: { uid: "demo-customer", email: "customer@zentro.test", name: "Customer Demo" },
  seller: { uid: "demo-seller", email: "seller@zentro.test", name: "Seller Demo" },
  admin: { uid: "demo-admin", email: "admin@zentro.test", name: "Admin Zentro" },
};

const TOKEN_KEY = "zentro.token";
const USER_KEY = "zentro.user";
const COOKIE = "zentro_auth";

/** Token dev (base64url klaim) yang cocok dengan FakeFirebaseTokenVerifier. */
function devToken(account: { uid: string; email: string; name: string }): string {
  const claims = {
    uid: account.uid,
    email: account.email,
    name: account.name,
    email_verified: true,
    exp: Math.floor(Date.now() / 1000) + 60 * 60,
  };

  return Buffer.from(JSON.stringify(claims), "utf8")
    .toString("base64")
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/, "");
}

/**
 * Menyiapkan sesi demo tanpa memanggil `/auth/firebase`.
 *
 * Endpoint auth dibatasi 10 permintaan/menit; menyuntikkan sesi langsung
 * membuat suite E2E tidak menyentuh batas tersebut. Data user tetap diambil
 * dari API `/me` oleh AuthProvider (sama seperti login sungguhan).
 */
export async function loginAs(page: Page, role: DemoRole): Promise<void> {
  const account = accounts[role];
  const token = devToken(account);
  const user = JSON.stringify({
    id: 0,
    firebase_uid: "",
    name: account.name,
    email: account.email,
    email_verified: true,
    phone: null,
    status: "active",
    roles: [],
    permissions: [],
    seller: null,
    created_at: null,
  });

  await page.addInitScript(
    ({ tokenKey, userKey, nextToken, nextUser, cookie }) => {
      sessionStorage.setItem(tokenKey, nextToken);
      sessionStorage.setItem(userKey, nextUser);
      document.cookie = `${cookie}=1; path=/; max-age=86400; samesite=lax`;
    },
    {
      tokenKey: TOKEN_KEY,
      userKey: USER_KEY,
      nextToken: token,
      nextUser: user,
      cookie: COOKIE,
    },
  );

  await page.goto("/");
  await page.waitForLoadState("domcontentloaded");
}

export async function logout(page: Page): Promise<void> {
  await page.getByRole("button", { name: /menu akun/i }).click();
  await page.getByRole("menuitem", { name: /keluar/i }).click();
  await expect(page).toHaveURL("/");
}
