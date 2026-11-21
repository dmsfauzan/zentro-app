import { test, expect } from "@playwright/test";
import { loginAs } from "../fixtures/auth";

test.describe("Alamat (direktori wilayah)", () => {
  test("memilih wilayah berjenjang dan mengosongkan turunannya saat provinsi diganti", async ({
    page,
  }) => {
    await loginAs(page, "customer");
    await page.goto("/akun/alamat");

    await page.getByRole("button", { name: /tambah alamat/i }).first().click();

    const provinsi = page.getByRole("combobox", { name: /^provinsi/i });
    const kota = page.getByRole("combobox", { name: /kota\/kabupaten/i });
    const kecamatan = page.getByRole("combobox", { name: /^kecamatan/i });
    const kelurahan = page.getByRole("combobox", { name: /kelurahan\/desa/i });
    const kodePos = page.locator("#postal_code");

    // Provinsi dimuat dari API publik.
    await expect(provinsi).toBeEnabled();
    await provinsi.selectOption({ label: "DKI Jakarta" });

    await expect(kota).toBeEnabled();
    await kota.selectOption({ label: "Kota Administrasi Jakarta Timur" });

    await expect(kecamatan).toBeEnabled();
    await kecamatan.selectOption({ label: "Matraman" });

    await expect(kelurahan).toBeEnabled();
    await kelurahan.selectOption({ label: "Pisangan Baru" });

    // Kode pos terisi otomatis dari kelurahan terpilih.
    await expect(kodePos).toHaveValue("13110");

    await page.locator("#recipient_name").fill("Customer Demo");
    await page.locator("#phone").fill("+628123456789");
    await page.locator("#address_line").fill("Jl. Matraman Raya No. 1");

    await page.getByRole("button", { name: /^simpan$/i }).click();

    await expect(page.getByText(/alamat ditambahkan/i)).toBeVisible();
    await expect(page.getByText(/Matraman/).first()).toBeVisible();

    // Buka lagi lalu ganti provinsi: turunan harus dikosongkan.
    await page.getByRole("button", { name: /tambah alamat/i }).first().click();
    await expect(provinsi).toBeEnabled();
    await provinsi.selectOption({ label: "DKI Jakarta" });
    await expect(kota).toBeEnabled();
    await kota.selectOption({ label: "Kota Administrasi Jakarta Selatan" });
    await expect(kecamatan).toBeEnabled();
    await kecamatan.selectOption({ label: "Kebayoran Baru" });
    await expect(kelurahan).toBeEnabled();

    await provinsi.selectOption({ label: "Bali" });

    await expect(kota).toHaveValue("");
    await expect(kecamatan).toHaveValue("");
    await expect(kelurahan).toHaveValue("");
    await expect(kodePos).toHaveValue("");
  });
});
