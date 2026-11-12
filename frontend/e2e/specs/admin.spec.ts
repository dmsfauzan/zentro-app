import { test, expect } from "@playwright/test";
import { loginAs } from "../fixtures/auth";

test.describe("Admin", () => {
  test("dashboard admin dapat dibuka", async ({ page }) => {
    await loginAs(page, "admin");
    await page.goto("/admin");

    await expect(page.getByRole("heading", { name: /ringkasan admin/i })).toBeVisible();
  });

  test("modul penjual dan payout dapat dibuka", async ({ page }) => {
    await loginAs(page, "admin");

    await page.goto("/admin/seller");
    await expect(page.getByRole("heading", { name: /penjual/i }).first()).toBeVisible();

    await page.goto("/admin/payout");
    await expect(page.getByRole("heading", { name: /payout/i }).first()).toBeVisible();
  });
});
