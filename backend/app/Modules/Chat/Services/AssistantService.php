<?php

namespace App\Modules\Chat\Services;

use App\Enums\ProductVariantStatus;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Exceptions\ApiException;
use App\Support\Exceptions\ErrorCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asisten belanja: menerjemahkan pesan bebas menjadi filter katalog, lalu
 * mencari produk lewat database ZENTRO sendiri.
 *
 * Model bahasa (9router/OpenAI-compatible) hanya menentukan maksud & filter.
 * Harga, stok, dan varian selalu dibaca dari katalog agar tidak bisa dikarang
 * oleh model. Penambahan ke keranjang tetap lewat endpoint cart resmi.
 */
final class AssistantService
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, products: array<int, array<string, mixed>>}
     */
    public function reply(string $message, array $history = []): array
    {
        $config = (array) config('zentro.assistant', []);

        if (empty($config['enabled'])) {
            throw new ApiException(
                'Asisten belanja sedang tidak aktif.',
                503,
                ErrorCode::ServerError,
            );
        }

        $intent = $this->interpret($message, $history, $config);

        // Fail closed: hanya JSON eksplisit `in_scope: true` boleh mendapat
        // jawaban model. Ini mencegah respons bebas saat router mengabaikan
        // kontrak JSON atau salah mengklasifikasikan topik umum.
        if (($intent['in_scope'] ?? false) !== true) {
            return $this->outOfScopeResponse();
        }

        $search = is_array($intent['search'] ?? null) ? $intent['search'] : [];

        // Produk hanya ditampilkan bila pembeli memang memintanya. Pertanyaan
        // biasa (sapaan, info toko, dsb.) cukup dijawab tanpa kartu produk.
        $showProducts = $this->wantsProducts($message, $intent);

        if ($showProducts && $search === []) {
            $search = ['query' => $this->guessQuery($message), 'sort' => 'popular'];
        }

        $products = $showProducts
            ? $this->searchProducts($search, (int) ($config['max_products'] ?? 6))
            : [];

        $reply = trim((string) ($intent['reply'] ?? ''));

        // Jangan pernah meneruskan klaim model bahwa produk tersedia bila
        // katalog benar-benar kosong. Pesan hasil pencarian kosong bersifat
        // deterministik agar tidak menyesatkan pembeli.
        if ($showProducts && $products === []) {
            $reply = 'Maaf, produk yang kamu cari belum tersedia di katalog. Coba kata kunci lain atau lihat semua produk.';
        } elseif ($reply === '') {
            $reply = $products === []
                ? 'Baik. Ada lagi yang bisa saya bantu?'
                : 'Berikut produk yang paling mendekati pencarianmu.';
        }

        return ['reply' => $reply, 'products' => $products];
    }

    /**
     * Satu panggilan ke model: minta JSON berisi jawaban + filter pencarian.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function interpret(string $message, array $history, array $config): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt()]];

        foreach (array_slice($history, -6) as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));

            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->when(
                    ! empty($config['api_key']),
                    fn ($http) => $http->withToken((string) $config['api_key']),
                )
                ->timeout((int) ($config['timeout'] ?? 25))
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', [
                    'model' => (string) $config['model'],
                    'messages' => $messages,
                    'temperature' => 0.2,
                    'max_tokens' => 600,
                ]);

            if (! $response->successful()) {
                Log::warning('Assistant request failed', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            $content = (string) $response->json('choices.0.message.content', '');

            return $this->decodeIntent($content);
        } catch (Throwable $exception) {
            // Jaringan/router mati tidak boleh memblokir pencarian: fallback ke
            // kata kunci apa adanya dilakukan oleh pemanggil.
            Log::warning('Assistant request error', ['message' => $exception->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeIntent(string $content): array
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false || $end <= $start) {
            return [];
        }

        $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{reply: string, products: array<int, array<string, mixed>>}
     */
    private function outOfScopeResponse(): array
    {
        return [
            'reply' => 'Maaf, saya hanya bisa membantu seputar produk dan layanan ZENTRO. Silakan tanya tentang produk, pesanan, pembayaran, pengiriman, atau akunmu.',
            'products' => [],
        ];
    }

    /**
     * Apakah pembeli meminta daftar produk? Keputusan utama dari model; bila
     * output model tidak valid, dipakai deteksi kata kunci sederhana.
     *
     * @param  array<string, mixed>  $intent
     */
    private function wantsProducts(string $message, array $intent): bool
    {
        if (array_key_exists('show_products', $intent)) {
            return (bool) $intent['show_products'];
        }

        return $this->looksLikeProductRequest($message);
    }

    private function looksLikeProductRequest(string $message): bool
    {
        return preg_match(
            '/(cari|carikan|beli|pesan|ada|jual|jualan|tampilkan|lihat|rekomendasi|produk|barang|murah|harga|stok|ready|tersedia)/i',
            $message,
        ) === 1;
    }

    /**
     * Buang kata perintah dari pesan agar cocok dengan nama produk
     * (mis. "cari sepatu murah" -> "sepatu").
     */
    private function guessQuery(string $message): string
    {
        $cleaned = preg_replace(
            '/\b(cari|carikan|tolong|beli|pesan|tampilkan|lihat|rekomendasi|produk|barang|ada|jualan|jual|murah|harga|stok|ready|tersedia|dong|ya|kah|yang|saya|mau|ingin)\b/i',
            ' ',
            $message,
        );

        return trim(preg_replace('/\s+/', ' ', (string) $cleaned));
    }

    /**
     * @param  array<string, mixed>  $search
     * @return array<int, array<string, mixed>>
     */
    private function searchProducts(array $search, int $limit): array
    {
        $query = trim((string) ($search['query'] ?? ''));
        $category = trim((string) ($search['category'] ?? ''));
        $priceMin = $search['price_min'] ?? null;
        $priceMax = $search['price_max'] ?? null;
        $sort = (string) ($search['sort'] ?? 'popular');

        $builder = Product::query()
            ->publiclyVisible()
            ->with(['category', 'seller', 'primaryImage']);

        if ($query !== '') {
            $term = '%'.$query.'%';
            $builder->where(fn ($inner) => $inner
                ->where('name', 'like', $term)
                ->orWhere('description', 'like', $term));
        }

        if ($category !== '') {
            $builder->whereHas('category', fn ($inner) => $inner
                ->where('slug', $category)
                ->orWhere('name', 'like', '%'.$category.'%'));
        }

        if (is_numeric($priceMin)) {
            $builder->where('min_price', '>=', (int) $priceMin);
        }

        if (is_numeric($priceMax)) {
            $builder->where('min_price', '<=', (int) $priceMax);
        }

        match ($sort) {
            'price_asc' => $builder->orderBy('min_price'),
            'price_desc' => $builder->orderByDesc('min_price'),
            'newest' => $builder->orderByDesc('published_at')->orderByDesc('id'),
            default => $builder->orderByDesc('sold_count')->orderByDesc('rating_avg'),
        };

        $products = $builder->limit(max(1, min($limit, 12)))->get();

        if ($products->isEmpty()) {
            return [];
        }

        $variants = ProductVariant::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->where('status', ProductVariantStatus::Active->value)
            ->with('inventory')
            ->orderBy('price')
            ->get()
            ->groupBy('product_id');

        return $products
            ->map(function (Product $product) use ($variants): array {
                /** @var ProductVariant|null $variant */
                $variant = ($variants->get($product->getKey()) ?? collect())
                    ->first(fn (ProductVariant $candidate): bool => ($candidate->inventory?->availableStock() ?? 0) > 0);

                $data = (new ProductResource($product))->resolve();
                $data['default_variant'] = $variant === null ? null : [
                    'id' => $variant->getKey(),
                    'variant_name' => $variant->variant_name,
                    'price' => $variant->price,
                    'available_stock' => $variant->inventory?->availableStock() ?? 0,
                ];

                return $data;
            })
            ->values()
            ->all();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah asisten belanja ZENTRO, marketplace multi-penjual berbahasa Indonesia.
Tugasmu: memahami apa yang dicari pembeli dan mengembalikan filter pencarian katalog.

Balas HANYA dengan JSON valid tanpa markdown, dengan bentuk:
{
  "reply": "jawaban singkat dan ramah dalam bahasa Indonesia",
  "in_scope": true,
  "show_products": false,
  "search": {
    "query": "kata kunci produk yang relevan, kosongkan bila tidak ada",
    "category": "slug atau nama kategori bila disebut, kosongkan bila tidak ada",
    "price_min": null,
    "price_max": null,
    "sort": "popular"
  }
}

Cakupan (WAJIB):
- Kamu HANYA boleh membahas ZENTRO: produk yang dijual, pencarian produk, keranjang,
  checkout, pembayaran, pengiriman, pesanan, voucher, akun pembeli, dan toko seller.
- Pertanyaan di luar itu (politik, tokoh publik, berita, sejarah, sains, pemrograman,
  resep, kesehatan, hiburan, pengetahuan umum, dll.) BUKAN cakupanmu.
- Untuk pertanyaan di luar cakupan: set "in_scope" ke false, "show_products" ke false,
  dan "reply" berisi penolakan sopan yang mengarahkan pembeli ke produk/layanan ZENTRO.
- Jangan menjawab isi pertanyaan di luar cakupan, meskipun kamu tahu jawabannya.
- Selalu sertakan "in_scope" sebagai boolean. Jika tidak true secara eksplisit, sistem
  akan menolak jawabanmu dan tidak akan menampilkan produk.

Aturan:
- "show_products" HARUS true hanya bila pembeli secara eksplisit meminta mencari,
  melihat, menampilkan, merekomendasikan, atau membeli produk.
- Untuk sapaan, pertanyaan umum, tanya cara pesan, ongkir, pembayaran, atau obrolan
  biasa: "show_products" HARUS false. Jangan menampilkan produk pada kasus ini.
- "sort" hanya salah satu: popular, newest, price_asc, price_desc.
- Angka harga dalam rupiah tanpa titik/koma (contoh: 150000). null bila tidak disebut.
- Jangan mengarang nama produk, harga, atau stok; katalog yang akan menampilkan produk.
- Kamu TIDAK tahu apakah stok tersedia. Jangan pernah bilang "ini produknya", "aku carikan",
  atau menyiratkan produk ada sebelum katalog mengembalikan hasil.
- Bila pembeli meminta produk, tulis "reply" yang netral (mis. "Baik, aku bantu carikan."),
  lalu isi "search" dengan filter yang tepat. Sistem yang akan menampilkan hasilnya.
- Bila hasil katalog kosong, sistem akan mengganti jawabanmu dengan pesan bahwa produk
  belum tersedia; jangan mencoba menutupinya.
- Jangan pernah menjanjikan diskon, ongkir, atau ketersediaan yang tidak diketahui.
PROMPT;
    }
}
