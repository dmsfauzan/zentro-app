<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Support\CatalogCache;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Menyimpan gambar produk ke disk publik dan mencatatnya.
 */
final class AddProductImageAction
{
    public function execute(
        Product $product,
        UploadedFile $file,
        ?string $altText = null,
        bool $makePrimary = false,
    ): ProductImage {
        $existing = ProductImage::query()->where('product_id', $product->getKey())->count();
        $max = (int) config('zentro.product_images_max_per_product', 5);

        if ($existing >= $max) {
            throw new ApiException(
                "Maksimal {$max} gambar per produk.",
                422,
                ErrorCode::ImageLimitReached,
                ['file' => ["Maksimal {$max} gambar per produk."]],
            );
        }

        /** @var string $disk */
        $disk = config('zentro.product_images_disk', 'product_images');

        return DB::transaction(function () use ($product, $file, $altText, $makePrimary, $existing, $disk): ProductImage {
            $path = $file->store((string) $product->getKey(), $disk);

            if (! is_string($path)) {
                throw new ApiException('Gambar gagal disimpan.', 500, ErrorCode::ServerError);
            }

            $isPrimary = $makePrimary || $existing === 0;

            if ($isPrimary) {
                ProductImage::query()
                    ->where('product_id', $product->getKey())
                    ->update(['is_primary' => false]);
            }

            $sortOrder = (int) ProductImage::query()
                ->where('product_id', $product->getKey())
                ->max('sort_order') + 1;

            $image = ProductImage::query()->create([
                'product_id' => $product->getKey(),
                'path' => $path,
                'alt_text' => $altText,
                'sort_order' => $sortOrder,
                'is_primary' => $isPrimary,
            ]);

            CatalogCache::bump();

            return $image;
        });
    }

    public function disk(): string
    {
        return (string) config('zentro.product_images_disk', 'product_images');
    }

    public function storage(): Filesystem
    {
        return Storage::disk($this->disk());
    }
}
