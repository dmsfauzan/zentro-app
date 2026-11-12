<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Support\CatalogCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Menghapus gambar produk dari disk dan basis data; mempromosikan
 * gambar utama baru bila yang dihapus adalah gambar utama.
 */
final class RemoveProductImageAction
{
    public function execute(ProductImage $image): void
    {
        /** @var string $disk */
        $disk = config('zentro.product_images_disk', 'product_images');
        $productId = $image->product_id;
        $wasPrimary = (bool) $image->is_primary;
        $path = $image->path;

        DB::transaction(function () use ($image, $productId, $wasPrimary): void {
            $image->delete();

            if ($wasPrimary) {
                $next = ProductImage::query()
                    ->where('product_id', $productId)
                    ->orderBy('sort_order')
                    ->first();

                $next?->update(['is_primary' => true]);
            }

            CatalogCache::bump();
        });

        Storage::disk($disk)->delete($path);
    }
}
