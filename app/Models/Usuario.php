<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    use HasUuids;
    use Notifiable;

    protected $table = 'usuarios';

    protected $guarded = [];

    protected $hidden = [
        'senha',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verificado_em' => 'immutable_datetime',
            'senha' => 'hashed',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'senha';
    }
}
