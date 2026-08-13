<?php

namespace App\Services;

use App\Models\AuditoriaEvento;
use App\Models\Implantacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditService
{
    private const PROTECTED_VALUE = '[DADO PROTEGIDO]';

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        string $module,
        string $entityType,
        string|int|null $entityId = null,
        array $changes = [],
        string $result = 'sucesso',
        ?string $justification = null,
        ?string $reasonCode = null,
        string $classification = 'interna',
        array $metadata = [],
        ?string $implantacaoId = null,
    ): AuditoriaEvento {
        $user = Auth::user();
        $request = app()->bound('request') ? request() : null;
        $correlationId = $this->correlationId($request);

        return DB::transaction(function () use (
            $action, $module, $entityType, $entityId, $changes, $result,
            $justification, $reasonCode, $classification, $metadata,
            $implantacaoId, $user, $request, $correlationId
        ): AuditoriaEvento {
            $event = AuditoriaEvento::query()->create([
                'implantacao_id' => $implantacaoId ?? Implantacao::current()->id,
                'occurred_at' => now(),
                'recorded_at' => now(),
                'actor_type' => $user ? 'usuario' : 'sistema',
                'actor_id' => $user?->getAuthIdentifier(),
                'actor_name' => $user?->name ?? 'Sistema',
                'actor_profile' => $this->actorProfile(),
                'session_hash' => $this->sessionHash($request),
                'action' => $action,
                'module' => $module,
                'entity_type' => $entityType,
                'entity_id' => $entityId === null ? null : (string) $entityId,
                'origin' => $request ? 'web' : 'sistema',
                'result' => $result,
                'reason_code' => $reasonCode,
                'justification' => $justification,
                'correlation_id' => $correlationId,
                'classification' => $classification,
            ]);

            foreach ($changes as $field => $change) {
                $masked = $this->isSensitiveField($field);
                $event->changes()->create([
                    'field_name' => $field,
                    'old_value' => $masked ? self::PROTECTED_VALUE : $this->normalizeValue($change['old'] ?? null),
                    'new_value' => $masked ? self::PROTECTED_VALUE : $this->normalizeValue($change['new'] ?? null),
                    'classification' => $masked ? 'restrita' : 'interna',
                    'is_masked' => $masked,
                ]);
            }

            $event->context()->create([
                'ip_address' => $request?->ip(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 1000, ''),
                'request_method' => $request?->method(),
                'request_path' => $this->requestPath($request),
                'metadata' => $this->sanitizeMetadata($metadata),
            ]);

            return $event->load(['changes', 'context']);
        });
    }

    public function recordModelChange(Model $model, string $operation): AuditoriaEvento
    {
        $attributes = match ($operation) {
            'criou' => $model->getAttributes(),
            'alterou' => $model->getChanges(),
            'excluiu' => $model->getOriginal(),
            default => [],
        };

        unset($attributes['created_at'], $attributes['updated_at'], $attributes['implantacao_id']);

        $changes = [];
        foreach ($attributes as $field => $newValue) {
            $changes[$field] = [
                'old' => $operation === 'criou' ? null : $model->getOriginal($field),
                'new' => $operation === 'excluiu' ? null : $newValue,
            ];
        }

        $justification = $model->getAttribute('status_reason')
            ?? $model->getAttribute('motivo_negacao')
            ?? $model->getAttribute('reason');

        return $this->record(
            action: $operation,
            module: $this->moduleFor($model),
            entityType: $model->getTable(),
            entityId: $model->getKey(),
            changes: $changes,
            justification: is_string($justification) ? $justification : null,
            implantacaoId: $model->getAttribute('implantacao_id'),
        );
    }

    private function actorProfile(): ?string
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return $user->usuarioPerfis()
            ->whereNull('ended_at')
            ->with('perfil:id,nome')
            ->get()
            ->pluck('perfil.nome')
            ->filter()
            ->implode(', ') ?: null;
    }

    private function sessionHash(?Request $request): ?string
    {
        if (! $request?->hasSession()) {
            return null;
        }

        return hash('sha256', $request->session()->getId());
    }

    private function correlationId(?Request $request): string
    {
        if (! $request) {
            return (string) Str::uuid7();
        }

        $existing = $request->attributes->get('_sdv_correlation_id');
        if (is_string($existing) && Str::isUuid($existing)) {
            return $existing;
        }

        $correlationId = (string) Str::uuid7();
        $request->attributes->set('_sdv_correlation_id', $correlationId);

        return $correlationId;
    }

    private function requestPath(?Request $request): ?string
    {
        if (! $request) {
            return null;
        }

        return $request->route()?->uri() ?? '/'.trim($request->path(), '/');
    }

    private function isSensitiveField(string $field): bool
    {
        return (bool) preg_match(
            '/password|senha|token|secret|segredo|remember|session|sessao|cpf|cnpj|document|biometr|facial|photo|foto|selfie|arquivo|file|conteudo|content/i',
            $field
        );
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && mb_strlen($value) > 1000) {
            return mb_substr($value, 0, 1000).'…';
        }

        return $value;
    }

    /** @param array<string, mixed> $metadata */
    private function sanitizeMetadata(array $metadata): array
    {
        return collect($metadata)->mapWithKeys(function (mixed $value, string $key): array {
            return [$key => $this->isSensitiveField($key) ? self::PROTECTED_VALUE : $this->normalizeValue($value)];
        })->all();
    }

    private function moduleFor(Model $model): string
    {
        return match (true) {
            Str::contains($model->getTable(), ['pre_registration']) => 'pre-cadastro',
            Str::contains($model->getTable(), ['pessoa', 'vinculo']) => 'pessoas',
            Str::contains($model->getTable(), ['imovel', 'bloco', 'endereco']) => 'imoveis',
            Str::contains($model->getTable(), ['veiculo']) => 'veiculos',
            Str::contains($model->getTable(), ['empresa']) => 'empresas',
            Str::contains($model->getTable(), ['encomenda']) => 'encomendas',
            Str::contains($model->getTable(), ['caixa']) => 'caixa',
            Str::contains($model->getTable(), ['historico_acessos']) => 'validacao',
            Str::contains($model->getTable(), ['user', 'usuario', 'perfil', 'permiss']) => 'usuarios',
            default => 'administracao',
        };
    }
}
