<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'ativo',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function pendente(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pendente',
            'invited_at' => now(),
        ]);
    }

    public function bloqueado(string $reason = 'Motivo de teste'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'bloqueado',
            'status_reason' => $reason,
            'status_changed_at' => now(),
        ]);
    }

    public function inativo(string $reason = 'Motivo de teste'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inativo',
            'status_reason' => $reason,
            'status_changed_at' => now(),
        ]);
    }
}
