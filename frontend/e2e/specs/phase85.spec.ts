import { test, expect } from "@playwright/test";
import { loginAs } from "../fixtures/auth";

test.describe("Phase 8.5", () => {
  test("admin membuka modul read-only baru dan voucher", async ({ page }) => {
    await loginAs(page, "admin");

    await page.goto("/admin/pesanan");
    await expect(page.getByRole("heading", { name: /^pesanan$/i }).first()).toBeVisible();

    await page.goto("/admin/pembayaran");
    await expect(page.getByRole("heading", { name: /^pembayaran$/i }).first()).toBeVisible();

    await page.goto("/admin/audit-log");
    await expect(page.getByRole("heading", { name: /audit log/i }).first()).toBeVisible();

    await page.goto("/admin/voucher");
    await expect(page.getByRole("heading", { name: /^voucher$/i }).first()).toBeVisible();
  });

  test("seller membuka halaman komisi", async ({ page }) => {
    await loginAs(page, "seller");

    await page.goto("/seller/komisi");
    await expect(page.getByRole("heading", { name: /^komisi$/i }).first()).toBeVisible();
  });

  test("customer membuka halaman notifikasi", async ({ page }) => {
    await loginAs(page, "customer");

    await page.goto("/notifikasi");
    await expect(page.getByRole("heading", { name: /^notifikasi$/i }).first()).toBeVisible();
  });
});
