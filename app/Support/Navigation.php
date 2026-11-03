<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class Navigation
{
    /** @return array<string, list<array{label: string, icon: string, route: string}>> */
    public static function groups(?User $user): array
    {
        $groups = [
            'Visão geral' => [
                ['label' => 'Dashboard', 'icon' => 'grid', 'route' => 'dashboard'],
            ],
            'Operação' => [
                ['label' => 'Modo Portaria', 'icon' => 'door', 'route' => 'portaria'],
                ['label' => 'Validação de entrada', 'icon' => 'shield', 'route' => 'validation'],
                ['label' => 'Pré-cadastros', 'icon' => 'badge-check', 'route' => 'pre-registrations'],
                ['label' => 'Entradas e saídas', 'icon' => 'door', 'route' => 'access-history'],
                ['label' => 'Caixa', 'icon' => 'clipboard', 'route' => 'cash-register'],
                ['label' => 'Encomendas', 'icon' => 'package', 'route' => 'packages'],
            ],
            'Cadastros' => [
                ['label' => 'Pessoas', 'icon' => 'users', 'route' => 'people.create'],
                ['label' => 'Imóveis', 'icon' => 'building', 'route' => 'properties'],
                ['label' => 'Veículos', 'icon' => 'car', 'route' => 'vehicles'],
                ['label' => 'Empresas e prestadores', 'icon' => 'users-round', 'route' => 'companies'],
            ],
            'Gestão' => [
                ['label' => 'Relatórios', 'icon' => 'chart', 'route' => 'reports'],
                ['label' => 'Logs e auditoria', 'icon' => 'scroll', 'route' => 'audit-log'],
            ],
            'Administração' => [
                ['label' => 'Usuários', 'icon' => 'users', 'route' => 'users'],
                ['label' => 'Perfis e permissões', 'icon' => 'key', 'route' => 'profiles'],
                ['label' => 'Configurações', 'icon' => 'settings', 'route' => 'settings'],
                ['label' => 'Motivos de negativa', 'icon' => 'file', 'route' => 'catalogs'],
            ],
            'Equipamentos' => [
                ['label' => 'Terminais', 'icon' => 'shield', 'route' => 'equipment'],
                ['label' => 'Pontos de acesso', 'icon' => 'door', 'route' => 'access-points'],
                ['label' => 'Câmeras de placas', 'icon' => 'car', 'route' => 'plate-cameras'],
                ['label' => 'Postos e operadores', 'icon' => 'users', 'route' => 'posts'],
            ],
        ];

        if ($user === null) {
            return [];
        }

        $permissions = $user->usuarioPerfis()
            ->whereNull('ended_at')
            ->whereHas('perfil', fn ($query) => $query->where('status', 'ativo'))
            ->with('perfil.permissoes')
            ->get()
            ->flatMap(fn ($assignment) => $assignment->perfil->permissoes->pluck('chave'))
            ->flip();

        // A mesma permissão da rota controla o menu; não manter duas matrizes.
        foreach ($groups as $label => $items) {
            $groups[$label] = array_values(array_filter($items, function (array $item) use ($permissions): bool {
                $route = Route::getRoutes()->getByName($item['route']);

                if ($route === null) {
                    return false;
                }

                foreach ($route->gatherMiddleware() as $middleware) {
                    if (str_starts_with($middleware, 'permissao:')) {
                        foreach (explode(',', substr($middleware, strlen('permissao:'))) as $permission) {
                            if ($permissions->has($permission)) {
                                return true;
                            }
                        }

                        return false;
                    }
                }

                return false;
            }));
        }

        return array_filter($groups);
    }

    public static function homeRoute(User $user): string
    {
        $items = collect(self::groups($user))->flatten(1);

        foreach (['dashboard', 'portaria', 'reports'] as $preferred) {
            if ($items->contains('route', $preferred)) {
                return $preferred;
            }
        }

        // Autoatendimento autenticado, inclusive para uma conta sem módulos.
        return $items->first()['route'] ?? 'sessions';
    }
}
