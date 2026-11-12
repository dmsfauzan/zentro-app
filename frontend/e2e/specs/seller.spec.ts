import { test, expect } from "@playwright/test";
import { loginAs } from "../fixtures/auth";

test.describe("Seller", () => {
  test("dashboard penjual dapat dibuka", async ({ page }) => {
    await loginAs(page, "seller");
    await page.goto("/seller");

    await expect(page.getByText(/perlu ditangani|belum punya toko/i).first()).toBeVisible();
  });

  test("halaman produk dan dompet dapat dibuka", async ({ page }) => {
    await loginAs(page, "seller");
    await page.goto("/seller/produk");
    await expect(page.getByRole("heading", { name: /produk/i }).first()).toBeVisible();

    await page.goto("/seller/dompet");
    await expect(page.getByRole("heading", { name: /dompet/i })).toBeVisible();
  });
});
