<?php

namespace Database\Factories;

use App\Enums\SellerAddressType;
use App\Modules\Seller\Models\Seller;
use App\Modules\Seller\Models\SellerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerAddress>
 */
class SellerAddressFactory extends Factory
{
    protected $model = SellerAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'type' => SellerAddressType::Origin,
            'label' => 'Gudang',
            'contact_name' => fake()->name(),
            'phone' => '+62812'.fake()->numerify('#######'),
            'address_line' => fake()->streetAddress(),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->city(),
            'village' => fake()->city(),
            'postal_code' => fake()->numerify('#####'),
            'latitude' => fake()->latitude(-11, 6),
            'longitude' => fake()->longitude(95, 141),
            'is_default' => true,
        ];
    }
}
