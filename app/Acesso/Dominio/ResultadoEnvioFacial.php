<?php

namespace App\Acesso\Dominio;

/**
 * Resultado do pedido de envio de uma pessoa ao terminal (AJ-006, fatia 6).
 * É separado do resultado do salvamento: uma falha aqui nunca desfaz o que
 * foi salvo. Não carrega nome, documento nem imagem.
 */
final readonly class ResultadoEnvioFacial
{
    /** @param  list<string>  $avisos */
    public function __construct(
        public string $situacao,
        public string $codigo,
        public string $mensagem,
        public ?string $estado = null,
        public ?string $operacaoId = null,
        public ?string $credencialId = null,
        public array $avisos = [],
    ) {}

    public static function recusado(string $codigo, string $mensagem): self
    {
        return new self('recusado', $codigo, $mensagem);
    }

    public function aceito(): bool
    {
        return $this->situacao !== 'recusado';
    }

    /** @return array{variant: string, title: string, message: string, avisos: list<string>} */
    public function paraTela(): array
    {
        [$variante, $titulo] = match (true) {
            $this->situacao === 'recusado' => ['danger', 'Envio para a facial não realizado'],
            $this->estado === 'sincronizado' => ['success', 'Pessoa cadastrada na facial'],
            in_array($this->estado, ['falha', 'intervencao_necessaria'], true) => ['danger', 'A facial não confirmou o cadastro'],
            $this->estado === 'atualizacao_pendente' => ['warning', 'Confirmação da facial pendente'],
            default => ['info', 'Envio para a facial na fila'],
        };

        return ['variant' => $variante, 'title' => $titulo, 'message' => $this->mensagem, 'avisos' => $this->avisos];
    }
}
