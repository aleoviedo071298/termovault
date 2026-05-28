<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Yacimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

class YacimientoFactory extends Factory
{
    protected $model = Yacimiento::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'nombre' => $this->faker->word() . ' ' . $this->faker->word(),
            'codigo' => $this->faker->unique()->numerify('YAC-####'),
            'activo' => true,
        ];
    }
}
