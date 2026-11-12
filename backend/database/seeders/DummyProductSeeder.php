<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Modules\Catalog\Actions\CreateProductAction;
use App\Modules\Catalog\Actions\EnsureProductPublishableAction;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mengisi katalog dengan 50 produk dummy lintas kategori.
 *
 * Idempotent: produk yang slug-nya sudah ada akan dilewati. Gambar dibuat
 * sebagai SVG placeholder (tanpa aset eksternal) lalu disimpan di disk
 * `product_images` yang sama dengan unggahan asli.
 */
class DummyProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $seller = $this->ensureSeller();
        $sellerUser = $seller->user;

        $create = app(CreateProductAction::class);
        $publish = app(EnsureProductPublishableAction::class);

        /** @var string $disk */
        $disk = config('zentro.product_images_disk', 'product_images');

        foreach ($this->catalog() as $index => $item) {
            $slug = Str::slug($item['name']);

            $existing = Product::withTrashed()->where('slug', $slug)->first();

            if ($existing instanceof Product) {
                // Refresh cover placeholder agar perubahan desain ikut terpakai.
                $primary = $existing->images()->where('is_primary', true)->first();

                if ($primary !== null && str_contains($primary->path, 'dummy-')) {
                    Storage::disk($disk)->put(
                        $primary->path,
                        $this->placeholderSvg($item['name'], $item['parent']),
                    );
                }

                continue;
            }

            $category = Category::query()
                ->where('slug', Str::slug($item['cat']))
                ->where('is_active', true)
                ->first()
                ?? Category::query()->where('slug', Str::slug($item['parent']))->first();

            if ($category === null) {
                continue;
            }

            $variants = [];

            foreach ($item['variants'] as $variantIndex => $variantName) {
                $variants[] = [
                    'sku' => sprintf('DUM-%03d-V%d', $index + 1, $variantIndex + 1),
                    'variant_name' => $variantName,
                    'price' => (int) round($item['price'] * (1 + 0.12 * $variantIndex)),
                    'weight_gram' => $item['weight'],
                    'stock' => 20 + (($index * 7 + $variantIndex * 13) % 80),
                ];
            }

            $product = $create->execute($seller, [
                'name' => $item['name'],
                'category_id' => $category->getKey(),
                'description' => sprintf(
                    '%s dari kategori %s. Produk pilihan dengan kualitas konsisten untuk kebutuhan harian.',
                    $item['name'],
                    $item['parent'],
                ),
                'weight_gram' => $item['weight'],
                'variants' => $variants,
            ], $sellerUser);

            $path = $product->getKey().'/dummy-'.Str::lower(Str::random(10)).'.svg';
            Storage::disk($disk)->put($path, $this->placeholderSvg($item['name'], $item['parent']));
            ProductImage::query()->create([
                'product_id' => $product->getKey(),
                'path' => $path,
                'alt_text' => $item['name'],
                'sort_order' => 1,
                'is_primary' => true,
            ]);

            $publish->apply($product, ProductStatus::Active);

            $product->forceFill([
                'is_featured' => $index % 7 === 0,
                'rating_avg' => 3.8 + (($index * 3) % 12) / 10,
                'rating_count' => 8 + (($index * 17) % 90),
                'sold_count' => 5 + (($index * 23) % 400),
            ])->save();
        }
    }

    private function ensureSeller(): Seller
    {
        $sellerUser = User::query()->where('firebase_uid', 'demo-seller')->first();

        if (! $sellerUser instanceof User) {
            $this->call(DemoUsersSeeder::class);
            $sellerUser = User::query()->where('firebase_uid', 'demo-seller')->firstOrFail();
        }

        $seller = Seller::query()->firstOrCreate(
            ['user_id' => $sellerUser->getKey()],
            [
                'store_name' => 'Toko Demo ZENTRO',
                'slug' => 'toko-demo-zentro',
                'description' => 'Toko contoh untuk data dummy katalog.',
                'status' => 'active',
                'submitted_at' => now(),
                'approved_at' => now(),
            ],
        );

        // Pastikan toko aktif agar produk tampil di katalog publik.
        $seller->forceFill([
            'status' => 'active',
            'submitted_at' => $seller->submitted_at ?? now(),
            'approved_at' => $seller->approved_at ?? now(),
        ])->save();

        return $seller->refresh();
    }

    /**
     * @return array<int, array{name: string, cat: string, parent: string, price: int, weight: int, variants: array<int, string>}>
     */
    private function catalog(): array
    {
        return [
            ['name' => 'Smartphone Nexus 5G', 'cat' => 'Smartphone', 'parent' => 'Elektronik', 'price' => 3_200_000, 'weight' => 180, 'variants' => ['128GB', '256GB']],
            ['name' => 'Laptop UltraBook 14 inci', 'cat' => 'Laptop', 'parent' => 'Elektronik', 'price' => 9_500_000, 'weight' => 1400, 'variants' => ['8GB/256GB', '16GB/512GB']],
            ['name' => 'TWS Earbuds Pro', 'cat' => 'Audio', 'parent' => 'Elektronik', 'price' => 450_000, 'weight' => 60, 'variants' => ['Putih', 'Hitam']],
            ['name' => 'Kamera Mirrorless X100', 'cat' => 'Kamera', 'parent' => 'Elektronik', 'price' => 7_800_000, 'weight' => 550, 'variants' => ['Body only', 'Kit 18-55mm']],
            ['name' => 'Power Bank 20000mAh', 'cat' => 'Aksesoris', 'parent' => 'Elektronik', 'price' => 250_000, 'weight' => 300, 'variants' => ['Hitam', 'Biru']],
            ['name' => 'Smartwatch Fit S2', 'cat' => 'Aksesoris', 'parent' => 'Elektronik', 'price' => 650_000, 'weight' => 90, 'variants' => ['Silikon', 'Milanese']],
            ['name' => 'Speaker Bluetooth Mini', 'cat' => 'Audio', 'parent' => 'Elektronik', 'price' => 320_000, 'weight' => 400, 'variants' => ['Merah', 'Abu']],
            ['name' => 'Keyboard Mekanik 65 Persen', 'cat' => 'Aksesoris', 'parent' => 'Elektronik', 'price' => 780_000, 'weight' => 700, 'variants' => ['Brown Switch', 'Red Switch']],

            ['name' => 'Kaos Premium Cotton', 'cat' => 'Pria', 'parent' => 'Fashion', 'price' => 120_000, 'weight' => 200, 'variants' => ['S', 'M', 'L', 'XL']],
            ['name' => 'Kemeja Oxford Slim', 'cat' => 'Pria', 'parent' => 'Fashion', 'price' => 210_000, 'weight' => 250, 'variants' => ['M', 'L', 'XL']],
            ['name' => 'Dress Linen Wanita', 'cat' => 'Wanita', 'parent' => 'Fashion', 'price' => 260_000, 'weight' => 300, 'variants' => ['S', 'M', 'L']],
            ['name' => 'Hoodie Fleece Oversize', 'cat' => 'Pria', 'parent' => 'Fashion', 'price' => 320_000, 'weight' => 600, 'variants' => ['M', 'L', 'XL']],
            ['name' => 'Sepatu Sneakers Urban', 'cat' => 'Sepatu', 'parent' => 'Fashion', 'price' => 480_000, 'weight' => 900, 'variants' => ['40', '41', '42', '43']],
            ['name' => 'Tote Bag Kanvas', 'cat' => 'Tas', 'parent' => 'Fashion', 'price' => 150_000, 'weight' => 350, 'variants' => ['Natural', 'Hitam']],
            ['name' => 'Blouse Satin', 'cat' => 'Wanita', 'parent' => 'Fashion', 'price' => 230_000, 'weight' => 250, 'variants' => ['S', 'M', 'L']],
            ['name' => 'Celana Chino Slim', 'cat' => 'Pria', 'parent' => 'Fashion', 'price' => 240_000, 'weight' => 450, 'variants' => ['30', '32', '34']],

            ['name' => 'Kopi Arabika Gayo 250g', 'cat' => 'Bahan Makanan', 'parent' => 'Makanan & Minuman', 'price' => 95_000, 'weight' => 250, 'variants' => ['Bubuk', 'Biji']],
            ['name' => 'Keripik Pisang Pedas', 'cat' => 'Makanan Ringan', 'parent' => 'Makanan & Minuman', 'price' => 25_000, 'weight' => 150, 'variants' => ['Original', 'Pedas']],
            ['name' => 'Teh Melati Premium', 'cat' => 'Minuman', 'parent' => 'Makanan & Minuman', 'price' => 35_000, 'weight' => 200, 'variants' => ['25 kantong', '50 kantong']],
            ['name' => 'Madu Hutan 500ml', 'cat' => 'Bahan Makanan', 'parent' => 'Makanan & Minuman', 'price' => 120_000, 'weight' => 600, 'variants' => ['500ml']],
            ['name' => 'Cokelat Dark 70 Persen', 'cat' => 'Makanan Ringan', 'parent' => 'Makanan & Minuman', 'price' => 45_000, 'weight' => 100, 'variants' => ['100g', '200g']],
            ['name' => 'Susu Almond 1 Liter', 'cat' => 'Minuman', 'parent' => 'Makanan & Minuman', 'price' => 65_000, 'weight' => 1000, 'variants' => ['Original', 'Cokelat']],
            ['name' => 'Bumbu Rendang Siap Pakai', 'cat' => 'Bahan Makanan', 'parent' => 'Makanan & Minuman', 'price' => 30_000, 'weight' => 200, 'variants' => ['200g']],

            ['name' => 'Serum Vitamin C 30ml', 'cat' => 'Skincare', 'parent' => 'Kecantikan', 'price' => 180_000, 'weight' => 100, 'variants' => ['30ml']],
            ['name' => 'Sunscreen SPF50 PA++++', 'cat' => 'Skincare', 'parent' => 'Kecantikan', 'price' => 135_000, 'weight' => 80, 'variants' => ['50ml']],
            ['name' => 'Lip Matte Velvet', 'cat' => 'Makeup', 'parent' => 'Kecantikan', 'price' => 95_000, 'weight' => 30, 'variants' => ['Nude', 'Rose', 'Terracotta']],
            ['name' => 'Cleanser Gentle Foam', 'cat' => 'Skincare', 'parent' => 'Kecantikan', 'price' => 85_000, 'weight' => 150, 'variants' => ['150ml']],
            ['name' => 'Body Lotion Shea Butter', 'cat' => 'Perawatan Tubuh', 'parent' => 'Kecantikan', 'price' => 110_000, 'weight' => 400, 'variants' => ['250ml']],
            ['name' => 'Masker Clay Detox', 'cat' => 'Skincare', 'parent' => 'Kecantikan', 'price' => 99_000, 'weight' => 120, 'variants' => ['100g']],

            ['name' => 'Wajan Anti Lengket 28cm', 'cat' => 'Peralatan Dapur', 'parent' => 'Rumah Tangga', 'price' => 220_000, 'weight' => 900, 'variants' => ['28cm']],
            ['name' => 'Set Pisau Dapur 5 pcs', 'cat' => 'Peralatan Dapur', 'parent' => 'Rumah Tangga', 'price' => 340_000, 'weight' => 1200, 'variants' => ['5 pcs']],
            ['name' => 'Vas Keramik Minimalis', 'cat' => 'Dekorasi', 'parent' => 'Rumah Tangga', 'price' => 145_000, 'weight' => 700, 'variants' => ['Putih', 'Sage']],
            ['name' => 'Lampu Meja LED', 'cat' => 'Dekorasi', 'parent' => 'Rumah Tangga', 'price' => 185_000, 'weight' => 800, 'variants' => ['Hangat', 'Putih']],
            ['name' => 'Pembersih Lantai 2 Liter', 'cat' => 'Kebersihan', 'parent' => 'Rumah Tangga', 'price' => 45_000, 'weight' => 2000, 'variants' => ['Lavender', 'Citrus']],
            ['name' => 'Tempat Sampah Sensor 12L', 'cat' => 'Kebersihan', 'parent' => 'Rumah Tangga', 'price' => 275_000, 'weight' => 1500, 'variants' => ['12L']],

            ['name' => 'Matras Yoga TPE', 'cat' => 'Perlengkapan Olahraga', 'parent' => 'Olahraga & Outdoor', 'price' => 190_000, 'weight' => 1000, 'variants' => ['6mm']],
            ['name' => 'Dumbbell Set 10kg', 'cat' => 'Perlengkapan Olahraga', 'parent' => 'Olahraga & Outdoor', 'price' => 450_000, 'weight' => 10000, 'variants' => ['10kg']],
            ['name' => 'Tenda Camping 2P', 'cat' => 'Outdoor', 'parent' => 'Olahraga & Outdoor', 'price' => 750_000, 'weight' => 2500, 'variants' => ['2 orang']],
            ['name' => 'Botol Minum Sport 750ml', 'cat' => 'Outdoor', 'parent' => 'Olahraga & Outdoor', 'price' => 89_000, 'weight' => 200, 'variants' => ['Biru', 'Hitam']],
            ['name' => 'Jaket Windbreaker', 'cat' => 'Outdoor', 'parent' => 'Olahraga & Outdoor', 'price' => 310_000, 'weight' => 400, 'variants' => ['M', 'L', 'XL']],

            ['name' => 'Vitamin D3 1000 IU', 'cat' => 'Suplemen', 'parent' => 'Kesehatan', 'price' => 90_000, 'weight' => 60, 'variants' => ['60 tablet']],
            ['name' => 'Masker Medis 3 Ply 50 pcs', 'cat' => 'Alat Kesehatan', 'parent' => 'Kesehatan', 'price' => 35_000, 'weight' => 200, 'variants' => ['50 pcs']],
            ['name' => 'Termometer Digital', 'cat' => 'Alat Kesehatan', 'parent' => 'Kesehatan', 'price' => 75_000, 'weight' => 100, 'variants' => ['Digital']],
            ['name' => 'Minyak Ikan Omega-3', 'cat' => 'Suplemen', 'parent' => 'Kesehatan', 'price' => 140_000, 'weight' => 200, 'variants' => ['90 softgel']],
            ['name' => 'Timbangan Digital Badan', 'cat' => 'Alat Kesehatan', 'parent' => 'Kesehatan', 'price' => 165_000, 'weight' => 1200, 'variants' => ['Hitam']],

            ['name' => 'Puzzle Kayu 1000 pcs', 'cat' => 'Mainan', 'parent' => 'Mainan & Hobi', 'price' => 120_000, 'weight' => 800, 'variants' => ['Lanskap', 'Hewan']],
            ['name' => 'Action Figure Robot', 'cat' => 'Koleksi', 'parent' => 'Mainan & Hobi', 'price' => 285_000, 'weight' => 500, 'variants' => ['Merah', 'Biru']],
            ['name' => 'Buku Resep Nusantara', 'cat' => 'Buku', 'parent' => 'Mainan & Hobi', 'price' => 89_000, 'weight' => 400, 'variants' => ['Softcover']],
            ['name' => 'Lego Bricks Set 500 pcs', 'cat' => 'Mainan', 'parent' => 'Mainan & Hobi', 'price' => 350_000, 'weight' => 900, 'variants' => ['500 pcs']],
            ['name' => 'Gitar Akustik 38 inci', 'cat' => 'Koleksi', 'parent' => 'Mainan & Hobi', 'price' => 950_000, 'weight' => 3000, 'variants' => ['Natural', 'Sunburst']],
        ];
    }

    private function placeholderSvg(string $name, string $parent): string
    {
        [$from, $to] = $this->palette($parent);

        $safeParent = htmlspecialchars(mb_strtoupper($parent), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $monogram = htmlspecialchars(
            mb_strtoupper(mb_substr($name, 0, 1)),
            ENT_QUOTES | ENT_XML1,
            'UTF-8',
        );

        $lines = array_slice(explode("\n", wordwrap($name, 16, "\n", true)), 0, 3);

        $tspans = '';
        foreach ($lines as $lineIndex => $line) {
            $safeLine = htmlspecialchars($line, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $tspans .= sprintf(
                '<tspan x="60" %s>%s</tspan>',
                $lineIndex === 0 ? 'y="520"' : 'dy="58"',
                $safeLine,
            );
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="750" viewBox="0 0 600 750">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$from}"/>
      <stop offset="1" stop-color="{$to}"/>
    </linearGradient>
  </defs>
  <rect width="600" height="750" fill="url(#g)"/>
  <circle cx="520" cy="110" r="190" fill="#ffffff" opacity="0.07"/>
  <circle cx="70" cy="700" r="160" fill="#ffffff" opacity="0.05"/>
  <text x="520" y="405" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="360" font-weight="700" fill="#ffffff" opacity="0.14">{$monogram}</text>
  <text x="60" y="110" font-family="Arial, Helvetica, sans-serif" font-size="22" letter-spacing="4" fill="#ffffff" opacity="0.8">{$safeParent}</text>
  <text font-family="Arial, Helvetica, sans-serif" font-size="52" font-weight="700" fill="#ffffff">{$tspans}</text>
  <text x="60" y="702" font-family="Arial, Helvetica, sans-serif" font-size="20" letter-spacing="5" fill="#ffffff" opacity="0.75">ZENTRO</text>
</svg>
SVG;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function palette(string $parent): array
    {
        return match ($parent) {
            'Elektronik' => ['#052440', '#0875E2'],
            'Fashion' => ['#0062D1', '#35A3FF'],
            'Makanan & Minuman' => ['#0B6BB3', '#7CC4FF'],
            'Kecantikan' => ['#4A5C6B', '#A8C0D6'],
            'Rumah Tangga' => ['#052440', '#4A5C6B'],
            'Olahraga & Outdoor' => ['#0F7B57', '#0875E2'],
            'Kesehatan' => ['#0875E2', '#0B6BB3'],
            'Mainan & Hobi' => ['#0062D1', '#052440'],
            default => ['#052440', '#0875E2'],
        };
    }
}
