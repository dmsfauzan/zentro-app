<?php

namespace Database\Factories;

use App\Enums\SellerStatus;
use App\Modules\Identity\Models\User;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    protected $model = Seller::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $storeName = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'store_name' => $storeName,
            'slug' => Str::slug($storeName).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'status' => SellerStatus::Pending,
            'rating_avg' => 0,
            'rating_count' => 0,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerStatus::UnderReview,
            'submitted_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerStatus::Active,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerStatus::Rejected,
            'submitted_at' => now(),
            'rejected_at' => now(),
            'rejection_reason' => 'Dokumen tidak jelas.',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => SellerStatus::Suspended,
            'approved_at' => now(),
            'suspended_at' => now(),
            'suspension_reason' => 'Pelanggaran kebijakan.',
        ]);
    }
}
