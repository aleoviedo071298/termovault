<?php

namespace Database\Factories;

use App\Models\Elemento;
use App\Models\Inspeccion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

class InspeccionFactory extends Factory
{
    protected $model = Inspeccion::class;

    public function definition(): array
    {
        return [
            'elemento_id' => Elemento::factory(),
            'tecnico_id' => Usuario::factory()->tecnico(),
            'fecha_inspeccion' => $this->faker->dateTime(),
            'cuadrilla' => 'Cuadrilla ' . $this->faker->word(),
            'integrantes' => $this->faker->numerify('##'),
            'empresa_contratista' => $this->faker->company(),
            'condiciones_clima' => $this->faker->word(),
            'resumen' => $this->faker->sentence(),
            'estado' => Inspeccion::ESTADO_ENVIADA,
            'created_by' => Usuario::factory()->tecnico(),
            'updated_by' => Usuario::factory()->tecnico(),
        ];
    }

    public function revisada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Inspeccion::ESTADO_REVISADA,
            'revisada_por' => Usuario::factory()->supervisor(),
            'fecha_revision' => $this->faker->dateTime(),
        ]);
    }

    public function cerrada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => Inspeccion::ESTADO_CERRADA,
            'revisada_por' => Usuario::factory()->supervisor(),
            'fecha_revision' => $this->faker->dateTime(),
            'cerrada_por' => Usuario::factory()->supervisor(),
            'fecha_cierre' => $this->faker->dateTime(),
        ]);
    }
}
