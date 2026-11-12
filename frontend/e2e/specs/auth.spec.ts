import { test, expect } from "@playwright/test";

test.describe("Auth & guard", () => {
  test("masuk sebagai customer lewat akun demo", async ({ page }) => {
    await page.goto("/login");
    await page.getByRole("button", { name: /customer/i }).first().click();

    await page.waitForURL("/");
    await page.getByRole("button", { name: /menu akun/i }).click();
    await page.getByRole("menuitem", { name: /pesanan/i }).click();
    await page.waitForURL(/\/pesanan/);
  });

  test("customer tidak bisa membuka area admin", async ({ page }) => {
    await page.goto("/login");
    await page.getByRole("button", { name: /customer/i }).first().click();
    await page.waitForURL("/");

    await page.goto("/admin");
    await page.getByRole("heading", { name: /tidak berhak/i }).waitFor();
  });

  test("link Masuk tetap berfungsi saat cookie sesi tertinggal", async ({ page, context }) => {
    await context.addCookies([
      { name: "zentro_auth", value: "1", url: "http://localhost:3000" },
    ]);

    await page.goto("/");
    await page.getByRole("link", { name: /^masuk$/i }).first().click();

    await expect(
      page.getByRole("heading", { name: /selamat datang kembali/i }),
    ).toBeVisible();
  });

  test("sesi aktif menawarkan Lanjutkan atau Ganti akun", async ({ page }) => {
    await page.goto("/login");
    await page.getByRole("button", { name: /customer/i }).first().click();
    await page.waitForURL("/");

    await page.goto("/login");
    await expect(page.getByRole("button", { name: /^lanjutkan$/i })).toBeVisible();

    await page.getByRole("button", { name: /ganti akun/i }).click();

    await expect(page.getByRole("button", { name: /customer/i }).first()).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });
});
