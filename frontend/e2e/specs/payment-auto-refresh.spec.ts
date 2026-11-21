import { test, expect, request, type APIRequestContext } from "@playwright/test";
import { loginAs } from "../fixtures/auth";

const API = process.env.E2E_API_URL ?? "http://localhost:8000/api/v1";

function demoToken(uid: string, email: string, name: string): string {
  const claims = {
    uid,
    email,
    name,
    email_verified: true,
    exp: Math.floor(Date.now() / 1000) + 60 * 60,
  };

  return Buffer.from(JSON.stringify(claims), "utf8")
    .toString("base64")
    .replace(/\+/g, "-")
    .replace(/\//g, "_")
    .replace(/=+$/, "");
}

function buyerApi(): Promise<APIRequestContext> {
  return request.newContext({
    extraHTTPHeaders: {
      Authorization: `Bearer ${demoToken("demo-customer", "customer@zentro.test", "Customer Demo")}`,
    },
  });
}

/** Membuat pesanan dengan pembayaran masih menunggu (pending). */
async function createPendingOrder(api: APIRequestContext): Promise<string> {
  const products = await (await api.get(`${API}/products?per_page=1`)).json();
  const slug = products?.data?.[0]?.slug as string;

  const detail = await (await api.get(`${API}/products/${slug}`)).json();
  const variantId = detail?.data?.variants?.[0]?.id as number;

  const addresses = await (await api.get(`${API}/me/addresses`)).json();
  const addressId = addresses?.data?.[0]?.id as number;

  const items = [{ variant_id: variantId, quantity: 1 }];

  const preview = await (
    await api.post(`${API}/checkout/preview`, { data: { items } })
  ).json();

  const selections = (preview?.data?.seller_groups ?? []).flatMap(
    (group: {
      seller: { id: number };
      selected_service_code?: string | null;
      shipping_options?: { code: string }[];
    }) => {
      const serviceCode =
        group.selected_service_code ?? group.shipping_options?.[0]?.code;

      return serviceCode ? [{ seller_id: group.seller.id, service_code: serviceCode }] : [];
    },
  );

  const created = await api.post(`${API}/checkout`, {
    headers: { "Idempotency-Key": `e2e-pay-${Date.now()}` },
    data: { items, address_id: addressId, shipping_selections: selections },
  });

  const body = await created.json();

  if (!created.ok()) {
    throw new Error(`Gagal checkout E2E: ${created.status()} ${JSON.stringify(body)}`);
  }

  return body.data.order_number as string;
}

test.describe("Status pembayaran", () => {
  test("memperbarui status otomatis tanpa refresh", async ({ page }) => {
    const api = await buyerApi();
    const orderNumber = await createPendingOrder(api);
    await api.dispose();

    await loginAs(page, "customer");

    const syncCalls: string[] = [];
    page.on("request", (req) => {
      if (req.method() === "POST" && req.url().includes("/payment/sync")) {
        syncCalls.push(req.url());
      }
    });

    await page.goto(`/pembayaran/${orderNumber}`);

    // Instruksi pembayaran tampil dan pengecekan otomatis berjalan.
    await expect(page.getByText(/instruksi pembayaran/i)).toBeVisible();
    await expect
      .poll(() => syncCalls.length, { timeout: 15_000 })
      .toBeGreaterThan(0);

    // Simulator lokal menandai lunas — tanpa reload halaman.
    const simulate = await request.newContext({
      extraHTTPHeaders: {
        Authorization: `Bearer ${demoToken("demo-customer", "customer@zentro.test", "Customer Demo")}`,
      },
    });

    await simulate.post(`${API}/dev/payments/${orderNumber}/simulate`, {
      data: { status: "paid" },
    });
    await simulate.dispose();

    await expect(
      page.getByText(/pembayaran diterima/i),
    ).toBeVisible({ timeout: 20_000 });

    // Polling berhenti setelah status final.
    const callsAtFinal = syncCalls.length;

    await page.waitForTimeout(9_000);
    expect(syncCalls.length).toBe(callsAtFinal);
  });
});
