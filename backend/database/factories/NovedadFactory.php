<?php

namespace Database\Factories;

use App\Models\Criticidad;
use App\Models\Inspeccion;
use App\Models\Novedad;
use Illuminate\Database\Eloquent\Factories\Factory;

class NovedadFactory extends Factory
{
    protected $model = Novedad::class;

    public function definition(): array
    {
        return [
            'inspeccion_id' => Inspeccion::factory(),
            'criticidad_id' => Criticidad::factory(),
            'titulo' => $this->faker->sentence(),
            'descripcion' => $this->faker->paragraph(),
            'ubicacion_dentro_elemento' => $this->faker->word(),
            'temperatura_detectada' => $this->faker->numerify('##.##'),
            'accion_recomendada' => $this->faker->sentence(),
            'estado' => Novedad::ESTADO_ABIERTA,
        ];
    }

    public function resuelta(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Novedad::ESTADO_RESUELTA,
        ]);
    }
}
