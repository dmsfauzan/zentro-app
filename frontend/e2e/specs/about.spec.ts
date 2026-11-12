import { test, expect } from "@playwright/test";

test.describe("Tentang", () => {
  test("halaman tentang terbuka dan menautkan FAQ", async ({ page }) => {
    const consoleErrors: string[] = [];

    page.on("console", (message) => {
      if (message.type() === "error") consoleErrors.push(message.text());
    });
    page.on("pageerror", (error) => consoleErrors.push(error.message));

    await page.goto("/tentang");

    await expect(page.getByRole("heading", { level: 1 })).toContainText(/banyak toko/i);

    await page.getByRole("link", { name: "FAQ" }).first().click();
    await expect(page).toHaveURL(/\/tentang\/faq$/);
    await expect(page.getByRole("heading", { level: 1 })).toContainText(/pertanyaan umum/i);

    const unexpected = consoleErrors.filter((message) => !/favicon|404/i.test(message));
    expect(unexpected).toEqual([]);
  });

  test("footer menautkan ke halaman perusahaan", async ({ page }) => {
    await page.goto("/tentang");

    const footer = page.getByRole("contentinfo");
    await expect(footer.getByRole("link", { name: /kebijakan privasi/i })).toBeVisible();

    await footer.getByRole("link", { name: /syarat/i }).click();
    await expect(page).toHaveURL(/\/tentang\/syarat-ketentuan$/);
    await expect(page.getByRole("heading", { level: 1 })).toContainText(/syarat/i);
  });

  test("sub-halaman tentang termuat", async ({ page }) => {
    const pages: [string, RegExp][] = [
      ["/tentang/kontak", /hubungi zentro/i],
      ["/tentang/kebijakan-privasi", /kebijakan privasi/i],
    ];

    for (const [path, heading] of pages) {
      await page.goto(path);
      await expect(page.getByRole("heading", { level: 1 })).toContainText(heading);
    }
  });
});
