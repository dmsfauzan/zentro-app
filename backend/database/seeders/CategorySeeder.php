<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Kategori awal marketplace. Idempotent (berdasarkan slug).
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Elektronik' => ['Smartphone', 'Laptop', 'Audio', 'Kamera', 'Aksesoris'],
            'Fashion' => ['Pria', 'Wanita', 'Anak', 'Sepatu', 'Tas'],
            'Makanan & Minuman' => ['Makanan Ringan', 'Minuman', 'Bahan Makanan'],
            'Kecantikan' => ['Skincare', 'Makeup', 'Perawatan Tubuh'],
            'Rumah Tangga' => ['Peralatan Dapur', 'Dekorasi', 'Kebersihan'],
            'Olahraga & Outdoor' => ['Perlengkapan Olahraga', 'Outdoor'],
            'Kesehatan' => ['Suplemen', 'Alat Kesehatan'],
            'Mainan & Hobi' => ['Mainan', 'Koleksi', 'Buku'],
        ];

        $sortOrder = 0;

        foreach ($tree as $parentName => $children) {
            $sortOrder += 10;

            $parent = Category::query()->updateOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'parent_id' => null,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ],
            );

            $childOrder = 0;

            foreach ($children as $childName) {
                $childOrder += 10;

                Category::query()->updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->getKey(),
                        'is_active' => true,
                        'sort_order' => $childOrder,
                    ],
                );
            }
        }
    }
}
