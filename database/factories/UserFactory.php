<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        $this->faker = \Faker\Factory::create('hu_HU'); // magyar nevekhez

        return [
            'name' => $this->faker->firstName . ' ' . $this->faker->lastName, // magyaros teljes név
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('Jelszo_2025'), // minden user jelszava: Jelszo_2025
            'role' => 'student',
        ];
    }
}
