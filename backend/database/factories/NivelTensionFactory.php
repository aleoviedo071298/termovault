<?php

namespace Database\Factories;

use App\Models\NivelTension;
use Illuminate\Database\Eloquent\Factories\Factory;

class NivelTensionFactory extends Factory
{
    protected $model = NivelTension::class;

    public function definition(): array
    {
        return [
            'kv' => $this->faker->numberBetween(10, 500),
            'etiqueta' => $this->faker->numerify('## kV'),
            'activo' => true,
        ];
    }
}
