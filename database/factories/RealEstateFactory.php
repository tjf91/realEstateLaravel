<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\RealEstate;

class RealEstateFactory extends Factory
{
    protected $model = RealEstate::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['house','department','land','commercial_ground']);

        // Bathrooms: zero ONLY if land or commercial_ground; otherwise allow decimals (x.0 / x.5)
        $bathrooms = in_array($type, ['land','commercial_ground'])
            ? 0.0
            : $this->faker->randomElement([1.0, 1.5, 2.0, 2.5, 3.0, 3.5]);

        // Rooms: required int — use 0 for land/commercial_ground to keep it realistic
        $rooms = in_array($type, ['land','commercial_ground'])
            ? 0
            : $this->faker->numberBetween(1, 6);

        // external_number: alphanumerics + dash, up to 12 chars
        $external = $this->faker->regexify('[A-Z0-9-]{1,12}');

        // internal_number: REQUIRED only for department/commercial_ground; else null
        $internal = in_array($type, ['department','commercial_ground'])
            // alphanumerics + dash + space, up to 12 chars
            ? $this->faker->regexify('[A-Z0-9 -]{1,12}')
            : null;

        return [
            'name'             => $this->faker->streetName(),                  // <=128
            'real_state_type'  => $type,
            'street'           => $this->faker->streetAddress(),               // <=128 (faker fits)
            'external_number'  => $external,
            'internal_number'  => $internal,
            'neighborhood'     => $this->faker->citySuffix(),                  // <=128
            'city'             => $this->faker->city(),                        // <=64
            'country'          => $this->faker->countryCode(),                 // ISO Alpha-2
            'rooms'            => $rooms,
            'bathrooms'        => $bathrooms,                                  // decimal(4,1)
            'comments'         => $this->faker->optional()->realText(60),      // <=128
        ];
    }
}
