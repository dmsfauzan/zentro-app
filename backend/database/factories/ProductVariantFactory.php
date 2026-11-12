<?php

namespace Database\Factories;

use App\Enums\ProductVariantStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.Str::upper(Str::random(10)),
            'variant_name' => fake()->randomElement(['Default', 'Merah / M', 'Biru / L']),
            'price' => fake()->numberBetween(10_000, 500_000),
            'weight_gram' => 500,
            'status' => ProductVariantStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ProductVariantStatus::Inactive]);
    }
}
