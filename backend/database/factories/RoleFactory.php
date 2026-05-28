<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'codigo' => $this->faker->unique()->numerify('ROL###'),
            'nombre' => $this->faker->word(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'codigo' => 'admin',
            'nombre' => 'admin',
        ]);
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'codigo' => 'supervisor',
            'nombre' => 'supervisor',
        ]);
    }

    public function tecnico(): static
    {
        return $this->state(fn (array $attributes) => [
            'codigo' => 'tecnico',
            'nombre' => 'tecnico',
        ]);
    }
}
