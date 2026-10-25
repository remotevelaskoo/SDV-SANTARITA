<div class="equipamento-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">{{ $feedback['message'] }}</x-ui.toast>
    @endif

    <x-ui.alert variant="info" title="Como a câmera é usada">
        O SDV pede uma imagem estática quando o operador clica em "Capturar imagem". Não há vídeo contínuo. O navegador recebe só o identificador da câmera; endereço, caminho e credencial ficam no servidor. Câmeras só RTSP/ONVIF usam um gateway de vídeo na rede interna que entrega o quadro por HTTP.
    </x-ui.alert>

    <x-ui.card title="Nova câmera de placas" description="Informe o caminho do snapshot sem usuário ou senha. Sem marca, modelo ou caminho conhecidos, use o simulador para validar o fluxo.">
        <form wire:submit="cadastrar" class="equipamento-management-form">
            <div class="equipamento-management-grid">
                <x-ui.field id="camera-nome" label="Nome" wire:model="form.nome" placeholder="Câmera entrada principal" :error="$errors->first('form.nome')" required />
                <x-ui.select id="camera-adaptador" label="Tipo de conexão" wire:model.live="form.adaptador">
                    @foreach ($adaptadores as $codigo => $rotulo)
                        <option value="{{ $codigo }}">{{ $rotulo }}</option>
                    @endforeach
                </x-ui.select>
                @if (! str_starts_with($form['adaptador'], 'simulador'))
                    <x-ui.field id="camera-fabricante" label="Fabricante" wire:model="form.fabricante" placeholder="Opcional" />
                    <x-ui.field id="camera-modelo" label="Modelo" wire:model="form.modelo" placeholder="Opcional" />
                    <x-ui.field id="camera-endereco" label="IP da câmera ou do gateway" wire:model="form.endereco_rede" placeholder="IP da rede local" autocomplete="off" />
                    <x-ui.field id="camera-porta" label="Porta" type="number" wire:model="form.porta_rede" placeholder="80" :error="$errors->first('form.porta_rede')" />
                    <x-ui.select id="camera-esquema" label="Protocolo" wire:model="form.esquema">
                        <option value="http">HTTP</option>
                        <option value="https">HTTPS</option>
                    </x-ui.select>
                    <x-ui.field id="camera-caminho" label="Caminho do snapshot" wire:model="form.caminho_snapshot" placeholder="/caminho/do/snapshot.jpg" help="Conforme o manual da câmera ou do gateway. Nunca inclua usuário ou senha." />
                @else
                    <x-ui.alert variant="warning" title="Simulador">Gera uma imagem sintética marcada como SIMULADOR. Não conecta em nenhuma câmera e não vale como evidência real.</x-ui.alert>
                @endif
                <x-ui.field id="camera-timeout" label="Tempo limite (s)" type="number" wire:model="form.timeout_segundos" :error="$errors->first('form.timeout_segundos')" />
            </div>
            <footer><x-ui.button type="submit" variant="primary">Cadastrar câmera</x-ui.button></footer>
        </form>
    </x-ui.card>

    @if ($ultimaCaptura)
        <x-ui.card title="Última captura de teste" :description="$ultimaCaptura->equipamento?->nome.' · '.($ultimaCaptura->pontoAcesso?->nome ?? 'sem ponto').' · '.$ultimaCaptura->capturada_em->format('d/m/Y H:i:s')">
            @if ($ultimaCaptura->simulada())
                <x-ui.badge variant="danger">SIMULADOR · imagem sintética</x-ui.badge>
            @endif
            @if ($podeVerImagem)
                <img class="camera-placas-teste" src="{{ route('protected-files.show', ['arquivo' => $ultimaCaptura->arquivo_id, 'contexto' => 'evidencia_placa']) }}" alt="Captura de teste" referrerpolicy="no-referrer">
            @else
                <p>Imagem guardada. Seu perfil não permite exibi-la.</p>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card title="Motivo para inativar" description="Obrigatório para inativar uma câmera. Fica na auditoria.">
        <div class="equipamento-management-form">
            <x-ui.field id="camera-motivo" label="Motivo" wire:model="motivo" placeholder="Ex.: câmera substituída" />
        </div>
    </x-ui.card>

    @forelse ($cameras as $camera)
        @php
            $vinculo = $camera->vinculoCameraVigente;
            $credencialAtiva = $camera->credenciaisTecnicas->firstWhere('status', 'ativa');
            $simulador = str_starts_with($camera->adaptador, 'simulador');
        @endphp
        <x-ui.card :title="$camera->nome" :description="(\App\Livewire\CameraPlacasManagement::ADAPTADORES[$camera->adaptador] ?? $camera->adaptador).($simulador ? '' : ' · '.$camera->enderecoMascarado())">
            <div class="equipamento-management-estado">
                <x-ui.badge :variant="$camera->status->value === 'ativo' ? 'success' : ($camera->status->value === 'inativo' ? 'neutral' : 'warning')">{{ $camera->status->rotulo() }}</x-ui.badge>
                <x-ui.badge :variant="$vinculo ? 'info' : 'warning'">{{ $vinculo ? 'Ponto: '.$vinculo->pontoAcesso->nome : 'Sem ponto' }}</x-ui.badge>
                @unless ($simulador)
                    <x-ui.badge :variant="$credencialAtiva ? 'success' : 'neutral'">{{ $credencialAtiva ? 'Credencial por referência' : 'Sem credencial' }}</x-ui.badge>
                @endunless
                @if ($simulador)
                    <x-ui.badge variant="danger">SIMULADOR</x-ui.badge>
                @endif
                @if ($camera->ultima_falha_at)
                    <x-ui.badge variant="warning">Última falha: {{ $camera->ultima_falha_at->format('d/m H:i') }}</x-ui.badge>
                @endif
            </div>

            @if ($camera->status->value !== 'inativo')
                <div class="equipamento-management-acoes">
                    <x-ui.select id="camera-ponto-{{ $camera->id }}" label="Ponto que a câmera enxerga" wire:model="pontoEscolhido.{{ $camera->id }}">
                        <option value="">Escolha</option>
                        @foreach ($pontos as $ponto)
                            <option value="{{ $ponto->id }}">{{ $ponto->nome }} ({{ $ponto->codigo }})</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button variant="secondary" size="sm" wire:click="vincular('{{ $camera->id }}')">{{ $vinculo ? 'Mover para o ponto' : 'Vincular ao ponto' }}</x-ui.button>
                    @if ($vinculo)
                        <x-ui.button variant="primary" size="sm" wire:click="testarCaptura('{{ $camera->id }}')" wire:loading.attr="disabled">Testar captura</x-ui.button>
                    @endif
                    <x-ui.button variant="danger" size="sm" wire:click="inativar('{{ $camera->id }}')">Inativar</x-ui.button>
                </div>

                @unless ($simulador)
                    <div class="equipamento-management-acoes">
                        <x-ui.field id="camera-ref-{{ $camera->id }}" label="Referência da senha" wire:model="credencial.{{ $camera->id }}.referencia" placeholder="env:NOME_DA_VARIAVEL" autocomplete="off" help="Somente a referência. A senha fica no ambiente do servidor." />
                        <x-ui.field id="camera-usuario-{{ $camera->id }}" label="Usuário técnico" wire:model="credencial.{{ $camera->id }}.usuario" autocomplete="off" />
                        <x-ui.button variant="secondary" size="sm" wire:click="definirCredencial('{{ $camera->id }}')">{{ $credencialAtiva ? 'Substituir credencial' : 'Registrar credencial' }}</x-ui.button>
                    </div>
                @endunless
            @endif
        </x-ui.card>
    @empty
        <x-ui.empty-state title="Nenhuma câmera de placas" description="Cadastre a primeira câmera acima. Sem câmera, a validação aceita a placa digitada." icon="car" />
    @endforelse
</div>
