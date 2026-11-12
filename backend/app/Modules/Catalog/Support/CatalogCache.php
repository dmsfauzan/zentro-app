<?php

namespace App\Modules\Catalog\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache katalog berbasis versi.
 *
 * Semua perubahan produk/varian/stok menaikkan versi sehingga key lama
 * otomatis tidak terpakai (tanpa perlu tags).
 */
final class CatalogCache
{
    public const VERSION_KEY = 'catalog.version';

    public const TTL_SECONDS = 300;

    public static function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, static fn (): int => 1);
    }

    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    public static function ttl(): int
    {
        return (int) config('zentro.catalog_cache_ttl', self::TTL_SECONDS);
    }

    public static function key(string $suffix): string
    {
        return "catalog.{$suffix}.v".self::version();
    }
}
