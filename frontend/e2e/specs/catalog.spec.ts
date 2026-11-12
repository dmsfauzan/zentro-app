import { test, expect } from "@playwright/test";

test.describe("Katalog", () => {
  test("membuka listing dan detail produk", async ({ page }) => {
    const consoleErrors: string[] = [];

    page.on("console", (message) => {
      if (message.type() === "error") consoleErrors.push(message.text());
    });
    page.on("pageerror", (error) => consoleErrors.push(error.message));

    await page.goto("/produk");

    await expect(page.getByRole("heading", { name: /semua produk/i })).toBeVisible();

    const firstProduct = page.locator('a[href^="/produk/"]').first();

    await expect(
      firstProduct.or(page.getByText(/produk tidak ditemukan/i)),
    ).toBeVisible({ timeout: 10_000 });

    if ((await page.getByText(/produk tidak ditemukan/i).count()) > 0) {
      test.skip(true, "Belum ada produk pada database E2E.");
    }

    // Gambar produk harus benar-benar termuat (bukan diblokir CSP).
    const cardImage = firstProduct.locator("img").first();
    await expect(cardImage).toBeVisible();
    await expect
      .poll(async () => cardImage.evaluate((el) => (el as HTMLImageElement).naturalWidth))
      .toBeGreaterThan(0);

    await firstProduct.click();
    await expect(page.getByRole("button", { name: /tambah ke keranjang/i })).toBeVisible();

    // Gambar utama PDP harus besar (bukan terjepit kolom thumbnail).
    const detailImage = page.locator("main img").first();
    await expect(detailImage).toBeVisible();
    const detailBox = await detailImage.boundingBox();
    expect(detailBox?.width ?? 0).toBeGreaterThan(250);

    const hydrationIssues = consoleErrors.filter((message) =>
      /hydration|cannot be a descendant|cannot contain a nested/i.test(message),
    );

    expect(hydrationIssues).toEqual([]);
  });

  test("filter kategori tersedia", async ({ page }) => {
    const consoleErrors: string[] = [];

    page.on("console", (message) => {
      if (message.type() === "error") consoleErrors.push(message.text());
    });
    page.on("pageerror", (error) => consoleErrors.push(error.message));

    await page.goto("/produk");
    await expect(page.getByText(/kategori/i).first()).toBeVisible();

    await page.getByLabel("Urutkan").selectOption("price_asc");

    const stateErrors = consoleErrors.filter((message) =>
      /Cannot update a component|hydration|nested/i.test(message),
    );

    expect(stateErrors).toEqual([]);
  });
});
