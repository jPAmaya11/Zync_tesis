<?php

namespace Modules\Role\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Role\Models\Role;


class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name'       => fake()->unique()->jobTitle(),
            'guard_name' => 'web',
        ];
    }
}