<?php

namespace Database\Factories;

use App\Models\Criticidad;
use App\Models\Elemento;
use App\Models\NivelTension;
use App\Models\TipoElemento;
use App\Models\Usuario;
use App\Models\Yacimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElementoFactory extends Factory
{
    protected $model = Elemento::class;

    public function definition(): array
    {
        return [
            'yacimiento_id' => Yacimiento::factory(),
            'tipo_elemento_id' => TipoElemento::factory(),
            'funcion' => $this->faker->word(),
            'nivel_tension_id' => NivelTension::factory(),
            'nombre' => $this->faker->word(),
            'codigo' => $this->faker->unique()->numerify('EL-####'),
            'marca' => $this->faker->company(),
            'modelo' => $this->faker->numerify('###'),
            'n_serie' => $this->faker->unique()->numerify('SN-#############'),
            'criticidad_id' => Criticidad::factory(),
            'estado_operativo' => 'operativo',
            'observaciones_generales' => $this->faker->sentence(),
            'created_by' => Usuario::factory(),
            'updated_by' => Usuario::factory(),
            'activo' => true,
        ];
    }
}
