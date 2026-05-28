<?php

namespace Database\Factories;

use App\Models\TipoElemento;
use Illuminate\Database\Eloquent\Factories\Factory;

class TipoElementoFactory extends Factory
{
    protected $model = TipoElemento::class;

    public function definition(): array
    {
        return [
            'codigo' => $this->faker->unique()->word(),
            'nombre' => $this->faker->word(),
            'prefijo_archivo' => $this->faker->numerify('TIP#'),
            'requiere_tension' => false,
            'activo' => true,
        ];
    }
}
