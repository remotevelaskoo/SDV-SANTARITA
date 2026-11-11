<?php

namespace Database\Factories;

use App\Models\PreRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreRegistration>
 */
class PreRegistrationFactory extends Factory
{
    protected $model = PreRegistration::class;

    /** Destinos fixos só para testes; o sistema usa o cadastro de imóveis (CatalogoDestinos). */
    private const DESTINOS = [
        'Bloco B — Apto 304' => 'Mariana Souza',
        'Bloco A — Apto 102' => 'Marcos Vinicius da Silva',
        'Bloco A — Apto 208' => 'Bianca Moretti',
    ];

    public function definition(): array
    {
        $start = now()->addDay()->setTime(14, 0);
        $property = array_key_first(self::DESTINOS);

        return [
            'protocol' => 'PRE-SRA-'.strtoupper(fake()->bothify('??####')),
            'name' => fake()->name(),
            'document' => '***.***.'.fake()->numerify('###').'-**',
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'phone' => fake()->numerify('(##) 9####-####'),
            'email' => fake()->unique()->safeEmail(),
            'access_type' => 'visitante',
            'address_informed' => fake()->streetAddress().' · '.fake()->city().'/SP',
            'destination_property' => $property,
            'destination_label' => $property,
            'responsible_name' => self::DESTINOS[$property] ?? null,
            'period_start' => $start,
            'period_end' => (clone $start)->addHours(4),
            'vehicle_plate' => null,
            'vehicle_model' => null,
            'vehicle_color' => null,
            'document_status' => 'Documento enviado e legível',
            'selfie_status' => 'Selfie enviada e adequada',
            'status' => 'aguardando',
            'alert' => null,
            'submitted_at' => now()->subHours(6),
            'status_changed_at' => null,
            'version' => 1,
        ];
    }

    public function tourist(): static
    {
        return $this->state(fn (array $attributes): array => [
            'access_type' => 'turista',
            'destination_property' => null,
            'destination_label' => 'Praia do Santa Rita',
            'responsible_name' => null,
        ]);
    }

    public function visitor(?string $property = null): static
    {
        $property ??= array_key_first(self::DESTINOS);

        return $this->state(fn (array $attributes): array => [
            'access_type' => 'visitante',
            'destination_property' => $property,
            'destination_label' => $property,
            'responsible_name' => self::DESTINOS[$property] ?? null,
        ]);
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'status_changed_at' => $status === 'aguardando' ? null : now()->subHour(),
        ]);
    }
}
