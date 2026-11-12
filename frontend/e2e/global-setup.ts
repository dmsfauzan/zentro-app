import { readFileSync } from "node:fs";
import { request, type APIRequestContext } from "@playwright/test";

const API = process.env.E2E_API_URL ?? "http://localhost:8000/api/v1";

function token(uid: string, email: string, name: string): string {
  const claims = {
    uid,
    email,
    name,
    email_verified: true,
    exp: Math.floor(Date.now() / 1000) + 60 * 60,
  };
  const json = JSON.stringify(claims);

  return Buffer.from(json, "utf8")
    .toString("base64")
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/, "");
}

async function contextFor(uid: string, email: string, name: string): Promise<APIRequestContext> {
  const anonymous = await request.newContext();
  const response = await anonymous.post(`${API}/auth/firebase`, {
    data: { id_token: token(uid, email, name) },
  });

  if (!response.ok()) {
    throw new Error(`Gagal login ${uid}: ${response.status()} ${await response.text()}`);
  }

  await anonymous.dispose();

  return request.newContext({
    extraHTTPHeaders: { Authorization: `Bearer ${token(uid, email, name)}` },
  });
}

/**
 * Menyiapkan data minimal untuk E2E lewat API (tanpa mengubah kode backend).
 * - Toko demo seller: ajukan, setujui, produk aktif, alamat buyer.
 */
export default async function globalSetup(): Promise<void> {
  const seller = await contextFor("demo-seller", "seller@zentro.test", "Seller Demo");
  const admin = await contextFor("demo-admin", "admin@zentro.test", "Admin Zentro");
  const buyer = await contextFor("demo-customer", "customer@zentro.test", "Customer Demo");

  try {
    // 1) Toko seller.
    let application = await seller.get(`${API}/seller-applications/me`);

    if (application.status() === 404) {
      const created = await seller.post(`${API}/seller-applications`, {
        data: {
          store_name: "Toko Demo E2E",
          description: "Toko untuk pengujian otomatis.",
          address: {
            contact_name: "Seller Demo",
            phone: "+628123456789",
            address_line: "Jl. Pengujian No. 1",
            province: "DKI Jakarta",
            city: "Jakarta Pusat",
            district: "Menteng",
            village: "Menteng",
            postal_code: "10310",
          },
        },
      });

      if (!created.ok()) {
        throw new Error(`Gagal membuat aplikasi seller: ${created.status()} ${await created.text()}`);
      }

      application = await seller.get(`${API}/seller-applications/me`);
    }

    const sellerBody = await application.json();
    const sellerId = sellerBody?.data?.id as number | undefined;
    let sellerStatus = sellerBody?.data?.status as string | undefined;

    if (sellerId && sellerStatus !== "active") {
      if (sellerStatus === "pending") {
        const ktp = readFileSync(
          "C:/laragon/www/Zentro/backend/tests/Fixtures/product-400.png",
        );

        const uploaded = await seller.post(
          `${API}/seller-applications/${sellerId}/documents`,
          {
            multipart: {
              type: "ktp",
              file: { name: "ktp.png", mimeType: "image/png", buffer: ktp },
            },
          },
        );

        if (!uploaded.ok() && uploaded.status() !== 409) {
          throw new Error(`Gagal mengunggah KTP E2E: ${uploaded.status()} ${await uploaded.text()}`);
        }
      }

      const approved = await admin.post(`${API}/admin/sellers/${sellerId}/approve`);

      if (!approved.ok() && ![409, 422].includes(approved.status())) {
        throw new Error(`Gagal menyetujui seller: ${approved.status()} ${await approved.text()}`);
      }

      const refreshed = await seller.get(`${API}/seller-applications/me`);
      sellerStatus = (await refreshed.json())?.data?.status;
    }

    if (sellerStatus !== "active") {
      throw new Error(`Toko demo seller tidak aktif (status: ${sellerStatus}).`);
    }

    // 2) Minimal satu produk aktif.
    const products = await seller.get(`${API}/seller/products?per_page=1`);
    const total = (await products.json())?.meta?.pagination?.total ?? 0;

    if (total === 0) {
      const publicApi = await request.newContext();
      const categories = await publicApi.get(`${API}/categories`);
      const categoryId = (await categories.json())?.data?.[0]?.id as number | undefined;
      await publicApi.dispose();

      const created = await seller.post(`${API}/seller/products`, {
        data: {
          name: `Produk E2E ${Date.now()}`,
          category_id: categoryId,
          description: "Produk yang dibuat otomatis untuk E2E.",
          weight_gram: 200,
          variants: [
            {
              sku: `E2E-${Date.now()}`,
              variant_name: "Default",
              price: 50000,
              weight_gram: 200,
              stock: 20,
            },
          ],
        },
      });

      const product = await created.json();
      const productId = product?.data?.id as number | undefined;

      if (!created.ok() || !productId) {
        throw new Error(`Gagal membuat produk E2E: ${created.status()} ${JSON.stringify(product)}`);
      }

      const image = readFileSync(
        "C:/laragon/www/Zentro/backend/tests/Fixtures/product-400.png",
      );

      const uploaded = await seller.post(`${API}/seller/products/${productId}/images`, {
        multipart: {
          file: { name: "product.png", mimeType: "image/png", buffer: image },
        },
      });

      if (!uploaded.ok()) {
        throw new Error(`Gagal mengunggah gambar E2E: ${uploaded.status()} ${await uploaded.text()}`);
      }

      const published = await seller.patch(`${API}/seller/products/${productId}`, {
        data: { status: "active" },
      });

      if (!published.ok()) {
        throw new Error(`Gagal menerbitkan produk E2E: ${published.status()} ${await published.text()}`);
      }
    }

    // 3) Alamat buyer (untuk checkout).
    const addresses = await buyer.get(`${API}/me/addresses`);
    const addressData = (await addresses.json())?.data ?? [];

    if (Array.isArray(addressData) && addressData.length === 0) {
      await buyer.post(`${API}/me/addresses`, {
        data: {
          label: "Rumah",
          recipient_name: "Customer Demo",
          phone: "+628123456789",
          address_line: "Jl. Pembeli No. 2",
          province: "DKI Jakarta",
          city: "Jakarta Selatan",
          district: "Kebayoran",
          village: "Kebayoran",
          postal_code: "12110",
          is_default: true,
        },
      });
    }
  } finally {
    await seller.dispose();
    await admin.dispose();
    await buyer.dispose();
  }
}
