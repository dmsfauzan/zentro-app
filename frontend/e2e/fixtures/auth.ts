import { expect, type Page } from "@playwright/test";

export type DemoRole = "customer" | "seller" | "admin";

const labels: Record<DemoRole, string> = {
  customer: "Customer",
  seller: "Seller",
  admin: "Admin",
};

/** Login lewat tombol akun demo (dev auth mode). */
export async function loginAs(page: Page, role: DemoRole): Promise<void> {
  await page.goto("/login");

  await expect(page.getByRole("heading", { name: /selamat datang/i })).toBeVisible();
  await page.getByRole("button", { name: new RegExp(labels[role], "i") }).first().click();

  await page.waitForURL((url) => !url.pathname.startsWith("/login"), { timeout: 15_000 });
}

export async function logout(page: Page): Promise<void> {
  await page.getByRole("button", { name: /menu akun/i }).click();
  await page.getByRole("menuitem", { name: /keluar/i }).click();
  await page.waitForURL("/");
}
