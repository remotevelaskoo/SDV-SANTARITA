<?php

namespace App\Acesso\Dominio;

/**
 * Resultado do pedido de envio de uma pessoa ao terminal (AJ-006, fatia 6).
 * É separado do resultado do salvamento: uma falha aqui nunca desfaz o que
 * foi salvo. Não carrega nome, documento nem imagem.
 *
 * AJ-010: o cadastro no terminal (usuário e validade) e a foto têm
 * resultados distintos; o primeiro nunca é apresentado como face.
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
        /** @var array{situacao: string, estado: ?string, mensagem: string}|null */
        public ?array $foto = null,
    ) {}

    /** @param  array{situacao: string, estado: ?string, mensagem: string}  $foto */
    public function comFoto(array $foto): self
    {
        return new self($this->situacao, $this->codigo, $this->mensagem, $this->estado, $this->operacaoId, $this->credencialId, $this->avisos, $foto);
    }

    public static function recusado(string $codigo, string $mensagem): self
    {
        return new self('recusado', $codigo, $mensagem);
    }

    public function aceito(): bool
    {
        return $this->situacao !== 'recusado';
    }

    /** @return array{variant: string, title: string, message: string, avisos: list<string>, foto: array{variant: string, title: string, message: string}|null} */
    public function paraTela(): array
    {
        [$variante, $titulo] = match (true) {
            $this->situacao === 'recusado' => ['danger', 'Envio para a facial não realizado'],
            $this->estado === 'sincronizado' => ['success', 'Cadastro confirmado pelo terminal'],
            in_array($this->estado, ['falha', 'intervencao_necessaria'], true) => ['danger', 'A facial não confirmou o cadastro'],
            $this->estado === 'atualizacao_pendente' => ['warning', 'Confirmação da facial pendente'],
            default => ['info', 'Envio para a facial na fila'],
        };

        return ['variant' => $variante, 'title' => $titulo, 'message' => $this->mensagem, 'avisos' => $this->avisos, 'foto' => $this->fotoParaTela()];
    }

    /** @return array{variant: string, title: string, message: string}|null */
    private function fotoParaTela(): ?array
    {
        if ($this->foto === null) {
            return null;
        }

        [$variante, $titulo] = match (true) {
            $this->foto['estado'] === 'sincronizado' => ['success', 'Foto confirmada pelo terminal'],
            in_array($this->foto['situacao'], ['pendente_habilitacao', 'pendente_homologacao'], true) => ['warning', 'Foto salva; envio facial pendente'],
            $this->foto['situacao'] === 'sem_foto' => ['info', 'Sem foto enviada'],
            $this->foto['situacao'] === 'recusado' || in_array($this->foto['estado'], ['falha', 'intervencao_necessaria'], true) => ['danger', 'A foto não chegou ao terminal'],
            $this->foto['estado'] === 'atualizacao_pendente' => ['warning', 'Confirmação da foto pendente'],
            default => ['info', 'Foto na fila de envio'],
        };

        return ['variant' => $variante, 'title' => $titulo, 'message' => $this->foto['mensagem']];
    }
}
