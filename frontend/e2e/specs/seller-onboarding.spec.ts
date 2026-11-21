import { test, expect } from "@playwright/test";
import { loginAs } from "../fixtures/auth";

test.describe("Onboarding penjual", () => {
  test("akun tanpa toko bisa membuka form buka toko lalu mengirim aplikasi", async ({
    page,
  }) => {
    await loginAs(page, "customer");

    await page.goto("/seller/onboarding");

    // Halaman onboarding tidak boleh tertutup guard "belum punya toko".
    await expect(page.getByRole("heading", { name: /^buka toko$/i })).toBeVisible();
    await expect(page.getByText(/akun ini belum punya toko/i)).toHaveCount(0);

    await page.locator("#store_name").fill("Toko E2E Onboarding");
    await page.locator("#description").fill("Toko untuk pengujian onboarding.");
    await page.locator("#contact_name").fill("Customer Demo");
    await page.locator("#phone").fill("+628123456789");
    await page.locator("#address_line").fill("Jl. Onboarding No. 5");

    const provinsi = page.getByRole("combobox", { name: /^provinsi/i });
    await expect(provinsi).toBeEnabled();
    await provinsi.selectOption({ label: "DKI Jakarta" });

    const kota = page.getByRole("combobox", { name: /kota\/kabupaten/i });
    await expect(kota).toBeEnabled();
    await kota.selectOption({ label: "Kota Administrasi Jakarta Pusat" });

    const kecamatan = page.getByRole("combobox", { name: /^kecamatan/i });
    await expect(kecamatan).toBeEnabled();
    await kecamatan.selectOption({ label: "Menteng" });

    const kelurahan = page.getByRole("combobox", { name: /kelurahan\/desa/i });
    await expect(kelurahan).toBeEnabled();
    await kelurahan.selectOption({ label: "Menteng" });

    await expect(page.locator("#postal_code")).not.toHaveValue("");

    await page.getByRole("button", { name: /kirim aplikasi/i }).click();

    // Setelah terkirim, halaman berubah ke ringkasan aplikasi toko.
    await expect(page.getByRole("heading", { name: /aplikasi toko/i })).toBeVisible({
      timeout: 15_000,
    });
    await expect(page.getByText("Toko E2E Onboarding")).toBeVisible();
  });
});
