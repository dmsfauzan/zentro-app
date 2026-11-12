import { test, expect } from "@playwright/test";
import { loginAs } from "../fixtures/auth";

test.describe("Pesanan", () => {
  test("daftar pesanan dapat dibuka", async ({ page }) => {
    await loginAs(page, "customer");
    await page.goto("/pesanan");

    await expect(page.getByRole("heading", { name: /pesanan saya/i })).toBeVisible();
  });

  test("halaman invoice menangani pesanan tidak dikenal", async ({ page }) => {
    await loginAs(page, "customer");
    await page.goto("/pesanan/ZTR-TIDAK-ADA/invoice");

    await expect(page.getByText(/invoice belum tersedia/i)).toBeVisible();
  });
});
