<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddress>
 */
class UserAddressFactory extends Factory
{
    protected $model = UserAddress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Rumah', 'Kantor', 'Kos']),
            'recipient_name' => fake()->name(),
            'phone' => '+62812'.fake()->numerify('#######'),
            'address_line' => fake()->streetAddress(),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'district' => fake()->city(),
            'village' => fake()->city(),
            'postal_code' => fake()->numerify('#####'),
            'latitude' => fake()->latitude(-11, 6),
            'longitude' => fake()->longitude(95, 141),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }
}
