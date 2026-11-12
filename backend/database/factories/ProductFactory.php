<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'seller_id' => Seller::factory(),
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'weight_gram' => 500,
            'status' => ProductStatus::Draft,
            'is_featured' => false,
            'rating_avg' => 0,
            'rating_count' => 0,
            'sold_count' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Inactive]);
    }
}
