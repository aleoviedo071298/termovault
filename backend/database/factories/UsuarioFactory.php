<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\Role;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'rol_id' => Role::factory(),
            'nombre' => $this->faker->firstName(),
            'apellido' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'activo' => true,
        ];
    }

    public function admin(): static
    {
        $role = Role::firstOrCreate(
            ['codigo' => 'admin'],
            ['nombre' => 'admin']
        );

        return $this->state(fn (array $attributes) => [
            'rol_id' => $role->id,
        ]);
    }

    public function supervisor(): static
    {
        $role = Role::firstOrCreate(
            ['codigo' => 'supervisor'],
            ['nombre' => 'supervisor']
        );

        return $this->state(fn (array $attributes) => [
            'rol_id' => $role->id,
        ]);
    }

    public function tecnico(): static
    {
        $role = Role::firstOrCreate(
            ['codigo' => 'tecnico'],
            ['nombre' => 'tecnico']
        );

        return $this->state(fn (array $attributes) => [
            'rol_id' => $role->id,
        ]);
    }
}
