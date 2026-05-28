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
            'nivel' => $this->faker->numberBetween(1, 5),
            'nombre' => $this->faker->word(),
            'color' => $this->faker->hexColor(),
        ];
    }
}
