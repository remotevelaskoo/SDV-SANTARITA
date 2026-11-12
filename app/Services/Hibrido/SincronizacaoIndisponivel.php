<?php

namespace App\Services\Hibrido;

use RuntimeException;

/** Sincronização não executada; a mensagem é própria do sistema e pode ser mostrada ao operador. */
class SincronizacaoIndisponivel extends RuntimeException {}
