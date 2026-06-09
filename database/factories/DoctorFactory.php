<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'           => fake()->name(),
            'email'          => fake()->unique()->safeEmail(),
            'phone'          => fake()->phoneNumber(),
            'specialization' => fake()->randomElement([
                'Cardiologist',
                'Dermatologist',
                'Neurologist',
                'Orthopedic',
                'Pediatrician',
                'Psychiatrist',
                'General Physician',
                'ENT Specialist',
            ]),
        ];
    }
}
