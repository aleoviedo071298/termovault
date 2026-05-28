<?php

namespace Database\Factories;

use App\Models\Criticidad;
use Illuminate\Database\Eloquent\Factories\Factory;

class CriticidadFactory extends Factory
{
    protected $model = Criticidad::class;

    public function definition(): array
    {
        return [
            'nivel' => $this->faker->unique()->numberBetween(1, 100),
            'nombre' => $this->faker->unique()->word(),
            'color' => $this->faker->hexColor(),
        ];
    }
}
