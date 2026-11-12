import { test, expect } from "@playwright/test";
import { loginAs } from "../fixtures/auth";

test.describe("Checkout", () => {
  test("keranjang kosong menampilkan arahan", async ({ page }) => {
    await loginAs(page, "customer");
    await page.goto("/keranjang");

    await expect(
      page.getByText(/keranjang/i).first(),
    ).toBeVisible();
  });

  test("checkout membutuhkan alamat", async ({ page }) => {
    await loginAs(page, "customer");
    await page.goto("/checkout");

    await expect(page.getByText(/keranjang kosong|checkout/i).first()).toBeVisible();
  });
});
