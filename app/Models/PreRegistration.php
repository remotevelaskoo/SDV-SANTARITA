<?php

namespace App\Models;

use App\Models\Concerns\BelongsToImplantacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// implantacao_id é fillable para os seeders (rodam com eventos de model desativados);
// em runtime normal, BelongsToImplantacao::bootBelongsToImplantacao() sobrescreve o valor no `creating`.
#[Fillable([
    'implantacao_id',
    'protocol', 'name', 'document', 'birth_date', 'phone', 'email',
    'access_type', 'address_informed',
    'destination_property', 'destination_label', 'responsible_name',
    'period_start', 'period_end',
    'vehicle_plate', 'vehicle_model', 'vehicle_color',
    'document_status', 'selfie_status',
    'status', 'alert', 'submitted_at', 'status_changed_at', 'version',
])]
class PreRegistration extends Model
{
    use BelongsToImplantacao, HasFactory, HasUuids;

    public const ACCESS_TYPES = ['turista', 'visitante', 'prestador'];

    public const STATUSES = ['aguardando', 'aprovado', 'rejeitado', 'correcao'];

    public const EDITABLE_STATUSES = ['aguardando'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'submitted_at' => 'datetime',
            'status_changed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /** @return HasMany<PreRegistrationEdit, $this> */
    public function edits(): HasMany
    {
        return $this->hasMany(PreRegistrationEdit::class)->orderBy('occurred_at');
    }

    /** @return HasMany<PreRegistrationArquivo, $this> */
    public function fileLinks(): HasMany
    {
        return $this->hasMany(PreRegistrationArquivo::class)->orderByDesc('linked_at');
    }

    public function currentFileLink(string $category): ?PreRegistrationArquivo
    {
        $links = $this->relationLoaded('fileLinks')
            ? $this->fileLinks
            : $this->fileLinks()->with('file')->get();

        return $links->first(fn (PreRegistrationArquivo $link) => $link->category === $category && $link->is_current);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function requiresProperty(): bool
    {
        return $this->access_type === 'visitante';
    }

    public function periodLabel(): string
    {
        $start = $this->period_start;
        $end = $this->period_end;

        if ($start->isSameDay($end)) {
            return sprintf('%s · %s às %s', $start->format('d/m/Y'), $start->format('H:i'), $end->format('H:i'));
        }

        if ($start->year === $end->year) {
            return sprintf('%s a %s', $start->format('d/m'), $end->format('d/m/Y'));
        }

        return sprintf('%s a %s', $start->format('d/m/Y'), $end->format('d/m/Y'));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    public function vehicleLabel(): string
    {
        return $this->vehicle_plate ?: 'Sem veículo';
    }
}
