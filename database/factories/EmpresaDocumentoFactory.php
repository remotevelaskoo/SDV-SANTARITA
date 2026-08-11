<?php

namespace Database\Factories;

use App\Models\Empresa;
use App\Models\EmpresaDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmpresaDocumento>
 */
class EmpresaDocumentoFactory extends Factory
{
    protected $model = EmpresaDocumento::class;

    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'tipo' => 'Contrato de prestação de serviço',
            'status' => 'validado',
        ];
    }
}
