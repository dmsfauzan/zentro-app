# 14 — Phase 3 Implementation Report (Catalog & Inventory)

**Date:** 2026-11-09
**Status:** ✅ Selesai & terverifikasi (87 test hijau, smoke test end-to-end)
**Scope:** Kategori, produk, varian, gambar, stok + pergerakan stok, katalog publik, moderasi admin.

---

## 1. Objective

Menyediakan modul katalog & inventori:

- Seller aktif mengelola produk, varian, gambar, dan stok.
- Produk tayang di katalog publik hanya bila memenuhi syarat (aktif + seller aktif + varian aktif).
- Setiap perubahan stok tercatat pada `inventory_movements` (append-only).
- Admin dapat memoderasi produk (reaktif).
- Katalog publik di-cache dan tetap konsisten setelah perubahan.

## 2. Architecture Decision

| Aspek | Keputusan |
|---|---|
| Modul | `app/Modules/Catalog` (produk/kategori/gambar) & `app/Modules/Inventory` (stok/pergerakan) |
| Publish rule | Produk dibuat `draft`; `active` hanya bila punya ≥1 varian aktif **dan** ≥1 gambar (`EnsureProductPublishableAction`) |
| Stok | `inventories` + `inventory_movements` append-only; update memakai `lockForUpdate()` |
| Invariant | `reserved_stock <= stock` (CHECK + guard aplikasi) |
| Kepemilikan | `ProductPolicy`, `ProductVariantPolicy`, `ProductImagePolicy` |
| Seller aktif | Middleware `seller.active` pada seluruh endpoint seller katalog |
| Cache | Berbasis versi (`CatalogCache::bump()`), TTL 300 detik; key lama otomatis tak terpakai |
| Gambar | Disk `product_images` → `public/uploads/products` (tanpa symlink, ramah Windows); produksi bisa diarahkan ke S3 |
| Moderasi (B-11) | Default **reaktif**: seller menerbitkan produk, admin dapat `active/inactive/rejected` |

**Asumsi/keputusan yang dipakai (B-11 & B-15 belum dijawab):**
- **B-11:** produk tayang tanpa approval awal; admin memoderasi reaktif.
- **B-15:** wilayah masih teks (ongkir menyusul di Phase 6).
- **Pencarian:** memakai `LIKE` untuk MVP; index FULLTEXT ditunda sampai volume menuntut (dicatat sebagai optimasi).

## 3. Files Created / Modified

### Dibuat
```
app/Enums/{ProductStatus,ProductVariantStatus,InventoryMovementType}.php
app/Modules/Catalog/Models/{Category,Product,ProductVariant,ProductImage}.php
app/Modules/Inventory/Models/{Inventory,InventoryMovement}.php
app/Modules/Catalog/Support/CatalogCache.php
app/Modules/Catalog/Actions/{CreateProductAction,UpdateProductAction,DeleteProductAction,
  CreateVariantWithInventoryAction,AddProductVariantAction,UpdateProductVariantAction,
  AddProductImageAction,RemoveProductImageAction,SyncProductPriceRangeAction,
  EnsureProductPublishableAction,ModerateProductAction}.php
app/Modules/Inventory/Actions/AdjustInventoryAction.php
app/Modules/Catalog/Http/Controllers/{CategoryController,ProductController}.php
app/Modules/Catalog/Http/Controllers/Seller/{ProductController,ProductImageController,ProductVariantController}.php
app/Modules/Catalog/Http/Controllers/Admin/ProductController.php
app/Modules/Inventory/Http/Controllers/Seller/InventoryController.php
app/Modules/Catalog/Http/Requests/{StoreProductRequest,UpdateProductRequest,StoreProductVariantRequest,
  UpdateProductVariantRequest,UploadProductImageRequest,ModerateProductRequest}.php
app/Modules/Inventory/Http/Requests/AdjustInventoryRequest.php
app/Modules/Catalog/Http/Resources/{CategoryResource,ProductResource,ProductDetailResource,
  ProductVariantResource,ProductImageResource}.php
app/Modules/Catalog/Policies/{ProductPolicy,ProductVariantPolicy,ProductImagePolicy}.php
app/Modules/Catalog/Routes/api.php
database/migrations/2026_11_09_1200{01..06}_*.php
database/factories/{CategoryFactory,ProductFactory,ProductVariantFactory,ProductImageFactory,InventoryFactory}.php
database/seeders/CategorySeeder.php
tests/Feature/Catalog/{CategoryTest,PublicProductTest,SellerProductTest,ProductImageTest,InventoryTest,AdminProductTest}.php
tests/Fixtures/{product-400.png,product-tiny.png}
```

### Dimodifikasi
```
app/Enums/UserStatus.php (tidak berubah) · app/Support/Exceptions/ErrorCode.php (+5 kode)
config/zentro.php (+media & cache) · config/filesystems.php (+disk product_images)
routes/api.php (memuat route Catalog) · database/seeders/DatabaseSeeder.php (+CategorySeeder)
tests/Pest.php (+helper makeSeller/productPayload/fixtureImage, fake disk)
```

## 4. Database Changes

6 migrasi baru:

| Tabel | Catatan |
|---|---|
| `categories` | self-reference `parent_id`, `slug` UNIQUE, index `(is_active, sort_order)` |
| `products` | FK seller/category RESTRICT, `slug` UNIQUE, soft delete, index `(seller_id,status)`, `(category_id,status)`, `(status,created_at)`, `(status,min_price)`, `(status,sold_count)` |
| `product_variants` | `sku` UNIQUE, CHECK `price >= 0` |
| `product_images` | FK produk CASCADE, index `(product_id, sort_order)` |
| `inventories` | `variant_id` UNIQUE, CHECK `reserved_stock <= stock` |
| `inventory_movements` | append-only (`created_at` saja), index `(inventory_id,created_at)` & `(reference_type,reference_id)` |

Seed: **34 kategori** (8 induk + 26 anak).

## 5. Implementation

**Publik (tanpa auth)**
- `GET /api/v1/categories` — tree kategori aktif (cache).
- `GET /api/v1/products` — filter `q`, `category`, `seller`, `price_min`, `price_max`, `featured`, `sort` (`newest|price_asc|price_desc|popular`), pagination.
- `GET /api/v1/products/{slug}` — detail + varian + gambar + seller.

**Seller (wajib seller aktif)**
- `GET/POST /seller/products`, `GET/PATCH/DELETE /seller/products/{product}`
- `POST /seller/products/{product}/images`, `DELETE /seller/products/{product}/images/{image}`
- `POST /seller/products/{product}/variants`
- `PATCH /seller/variants/{variant}`
- `PATCH /seller/variants/{variant}/inventory` (`mode=set|adjust`)

**Admin (`manage_products`)**
- `GET /admin/products` (filter status/seller/q)
- `PATCH /admin/products/{product}/status` (`active|inactive|rejected`)

**Aturan penting**
- `min_price`/`max_price` disinkronkan dari varian aktif.
- Harga uang = integer rupiah.
- `inventory_movements` mencatat `stock_before/after`, `reserved_before/after`, aktor, dan referensi.
- Soft delete produk → status `archived` + varian `inactive`.
- Slug produk **stabil** saat nama berubah (menjaga URL).

## 6. Security Considerations

| Kontrol | Status |
|---|---|
| Kepemilikan produk/varian/gambar | ✅ Policy + test 403 |
| Seller non-aktif tidak bisa mengelola katalog | ✅ `seller.active` + test |
| Validasi unggahan gambar (MIME, ukuran, dimensi) | ✅ (`getimagesize`, tanpa GD) |
| Batas jumlah gambar per produk | ✅ (default 5) |
| Dokumen/gambar tidak menimpa file lain | ✅ nama acak |
| Admin moderation butuh permission | ✅ `manage_products` |
| Cache tidak menyajikan data usang setelah mutasi | ✅ version bump |
| Anti race condition stok | ✅ `lockForUpdate` + CHECK DB |
| Input uang tidak dari klien | ✅ harga dari payload seller, bukan pembeli |

## 7. Testing Results

```
Backend : 87 passed (301 assertions) — Pest 4.7.8   [29 test baru Phase 3]
Pint    : OK
Smoke   : kategori → daftar seller → approve → buat produk (draft) → upload gambar
          → publish (active) → listing publik (total=1) → detail (stok 25) ✅
```

Cakupan Phase 3:
- Kategori: tree publik, hanya aktif.
- Publik: hanya produk layak tampil; detail by slug; 404 untuk draft; filter harga.
- Seller: guard seller aktif; create + inventory + movement; kategori nonaktif ditolak; SKU duplikat; publish tanpa gambar ditolak; publish setelah gambar sukses; isolasi lintas seller; soft delete + arsip varian.
- Gambar: unggah + primary otomatis; MIME salah ditolak; dimensi kecil ditolak; batas jumlah; hapus + promosi primary; lintas seller 403.
- Inventori: set/adjust + movement; tidak boleh < reserved; tidak boleh negatif; lintas seller 403.
- Admin: non-admin 403; listing + filter; moderasi inactive/rejected; aktivasi tanpa syarat 422; aktivasi dengan syarat sukses.

**Bug yang ditemukan test:** `$data` tidak ditangkap closure di `AdjustInventoryAction` → diperbaiki sebelum rilis.

## 8. How to Run

```powershell
cd C:\laragon\www\onlineshop\backend
php artisan migrate            # atau migrate:fresh --seed untuk data bersih
php artisan serve
php artisan test
```

Contoh cepat (setelah seller aktif):
```http
POST /api/v1/seller/products
{ "name": "Kaos", "category_id": 2, "variants": [{ "sku": "KZ-1", "variant_name": "M", "price": 75000, "stock": 10 }] }

POST /api/v1/seller/products/{id}/images   (multipart: file)
PATCH /api/v1/seller/products/{id}         { "status": "active" }
GET  /api/v1/products
```
Gambar tersimpan di `public/uploads/products/{product_id}/`.

## 9. Known Limitations

1. **Pencarian memakai `LIKE`** (belum FULLTEXT/search engine); cukup untuk MVP tetapi kurang optimal pada volume besar.
2. **Belum ada varian multi-atribut** (ukuran/warna sebagai struktur); saat ini `variant_name` teks bebas.
3. **Belum ada endpoint admin untuk CRUD kategori** — kategori dari seeder; penambahan via DB/seeder.
4. **Hapus produk** belum menolak bila produk punya riwayat pesanan (tabel order belum ada); akan diguard di Phase 4.
5. **`sold_count`/`rating` belum dihitung** (menunggu order & review).
6. **Gambar disimpan lokal** di `public/uploads`; produksi perlu disk S3 + CDN.
7. **`stats_available` dashboard seller** masih `false` walaupun produk sudah ada — penghitungan metrik dashboard belum diaktifkan.
8. **Batas 5 gambar/produk** bersifat konfigurasi, belum ada UI pengurutan manual (sort_order otomatis).

## 10. Next Phase

**Phase 4 — Cart & Checkout**: `carts`, `cart_items`, `orders`, `seller_orders`, `order_items`, `order_addresses`, status histories, `inventory_reservations`, `idempotency_keys`; checkout preview & checkout dengan penguncian stok atomik, split per seller, snapshot, dan uji konkurensi overselling.

**Prasyarat keputusan:** **B-8** (timeout pembayaran/reservasi), **B-3/B-6** (basis komisi & penanggung voucher) mulai relevan di perhitungan checkout; bila belum dijawab saya pakai default: timeout 24 jam, komisi dari subtotal produk (ongkir tidak termasuk), voucher platform ditanggung platform.
