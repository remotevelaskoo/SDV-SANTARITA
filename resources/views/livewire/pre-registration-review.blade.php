@php
    $statusVariant = match ($record->status) { 'aprovado' => 'success', 'rejeitado' => 'danger', 'correcao' => 'warning', default => 'info' };
    $statusLabel = match ($record->status) { 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado', 'correcao' => 'Correção solicitada', default => 'Aguardando análise' };
    $mostrarSensivel = fn (?string $valor) => $valor === null ? '—' : ($documentRevealed ? $valor : \App\Support\NormalizadorBusca::mascararDocumento($valor));
    $valorOcr = fn (array $linha, string $chave) => $linha['sensivel'] ? $mostrarSensivel($linha[$chave]) : ($linha[$chave] ?? '—');
    $podeDecidir = $pode['editar'] || $pode['corrigir'] || $pode['rejeitar'] || $pode['aprovar'];
@endphp

{{-- SDV-OPE-020 §13: cabeçalho, pendências, conferência, divergências, decisão e detalhes recolhidos. --}}
<div class="pre-cadastro-analise">
    <nav class="pre-cadastro-analise__voltar" aria-label="Navegação da análise">
        <a href="{{ route('pre-registrations') }}">← Voltar à fila de pré-cadastros</a>
    </nav>

    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @error('state')
        <x-ui.alert variant="danger" title="Ação não realizada">{{ $message }}</x-ui.alert>
    @enderror
    @error('editReason')
        @unless ($editing)
            <x-ui.alert variant="danger" title="Conflito ou pendência na edição">{{ $message }}</x-ui.alert>
        @endunless
    @enderror

    <header class="pre-cadastro-analise__pessoa">
        <span class="pre-registration-person-avatar" aria-hidden="true">{{ $record->initials() }}</span>
        <div>
            <h2>{{ $record->name }}</h2>
            <p>{{ ucfirst($record->access_type) }} · {{ $record->destination_label }} · {{ $record->periodLabel() }}</p>
        </div>
        <div class="pre-cadastro-analise__situacao">
            <x-ui.badge :variant="$statusVariant">{{ $statusLabel }}</x-ui.badge>
            <small>Protocolo {{ $record->protocol }}</small>
        </div>
    </header>

    <section @class(['pre-cadastro-analise__pendencias', 'is-vazio' => $alerts === []]) aria-labelledby="analise-pendencias-titulo">
        <h3 id="analise-pendencias-titulo">
            @if ($alerts === [])
                Nenhuma pendência encontrada
            @else
                Pendências para conferir <span class="pre-cadastro-analise__contador">{{ count($alerts) }}</span>
            @endif
        </h3>
        @if ($alerts !== [])
            <ul>
                @foreach ($alerts as $alert)
                    <li @class(['is-'.$alert['variant']])>
                        <strong>{{ $alert['title'] }}</strong>
                        <span>{{ $alert['message'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="pre-cadastro-analise__grade">
        <section class="pre-cadastro-analise__imagens" aria-labelledby="analise-imagens-titulo" x-data="{ exibir: false, ampliada: null, titulo: '' }">
            <header>
                <div>
                    <h3 id="analise-imagens-titulo">Conferência visual protegida</h3>
                    <p>A selfie só carrega depois do clique, e cada abertura é registrada na auditoria. Ver a selfie não cria credencial biométrica nem autoriza entrada.</p>
                </div>
                <x-ui.badge variant="warning">Dados sensíveis</x-ui.badge>
            </header>

            @if ($podeVerImagens)
                <div class="pre-cadastro-analise__exibir" x-show="!exibir">
                    <x-ui.button variant="secondary" x-on:click="exibir = true">Exibir selfie para conferência</x-ui.button>
                </div>
            @else
                <x-ui.alert variant="info" title="Selfie protegida pelo seu perfil">
                    Seu perfil não permite abrir a selfie. Analise os dados ou peça apoio a quem tem a permissão.
                </x-ui.alert>
            @endif

            <ul class="pre-cadastro-analise__galeria" aria-label="Selfie para conferência">
                @foreach ($imagens as $imagem)
                    <li @class(['pre-cadastro-analise__figura', 'is-ausente' => ! $imagem['arquivo']])>
                        <div class="pre-cadastro-analise__moldura">
                            @if ($imagem['url'] && $podeVerImagens)
                                <img
                                    x-show="exibir"
                                    x-bind:src="exibir ? @js($imagem['url']) : null"
                                    alt="{{ $imagem['rotulo'] }} de {{ $record->name }}"
                                    data-arquivo="{{ $imagem['arquivo']->id }}"
                                    referrerpolicy="no-referrer"
                                >
                                <span x-show="!exibir" class="pre-cadastro-analise__oculta"><x-icon :name="$imagem['categoria'] === 'selfie' || $imagem['categoria'] === 'documento_foto' ? 'users' : 'file'" /> Imagem oculta</span>
                            @elseif ($imagem['arquivo'])
                                <span class="pre-cadastro-analise__oculta">{{ $podeVerImagens ? 'Arquivo '.str_replace('_', ' ', $imagem['arquivo']->status) : 'Imagem protegida' }}</span>
                            @else
                                <span class="pre-cadastro-analise__oculta">Não enviado neste pré-cadastro</span>
                            @endif
                        </div>
                        <div class="pre-cadastro-analise__legenda">
                            <strong>{{ $imagem['rotulo'] }}</strong>
                            @if ($imagem['url'] && $podeVerImagens)
                                <button type="button" class="sensitive-reveal" x-show="exibir" x-on:click="titulo = @js($imagem['rotulo']); ampliada = @js($imagem['url']); $nextTick(() => $refs.ampliar.showModal())" aria-label="Ampliar {{ $imagem['rotulo'] }}">Ampliar</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <dialog x-ref="ampliar" class="protected-file-viewer" aria-labelledby="analise-ampliada-titulo" x-on:close="ampliada = null" @click.self="$el.close()">
                <div class="protected-file-viewer__surface">
                    <header>
                        <div>
                            <h4 id="analise-ampliada-titulo" x-text="titulo"></h4>
                            <p>Visualização temporária para conferência. Download não é oferecido.</p>
                        </div>
                        <form method="dialog"><x-ui.button type="submit" variant="ghost" size="sm">Fechar</x-ui.button></form>
                    </header>
                    <div class="protected-file-viewer__canvas">
                        <img x-show="ampliada" x-bind:src="ampliada" x-bind:alt="titulo" />
                    </div>
                </div>
            </dialog>
        </section>

        <div class="pre-cadastro-analise__dados">
            <section class="pre-cadastro-analise__bloco" aria-labelledby="analise-dados-titulo">
                <header>
                    <h3 id="analise-dados-titulo">Dados essenciais</h3>
                    @if ($podeRevelar)
                        <button type="button" class="sensitive-reveal" wire:click="toggleDocumentReveal">
                            {{ $documentRevealed ? 'Ocultar documentos' : 'Revelar documentos' }}
                        </button>
                    @endif
                </header>
                <dl class="pre-cadastro-analise__essenciais">
                    <div><dt>Nome completo</dt><dd>{{ $record->name }}</dd></div>
                    <div><dt>Documento</dt><dd>{{ $documentRevealed ? $record->document : $record->maskedDocument() }}</dd></div>
                    <div><dt>Data de nascimento</dt><dd>{{ $record->birth_date?->format('d/m/Y') ?? 'Não informada' }}</dd></div>
                    <div><dt>Telefone</dt><dd>{{ $record->phone ?: 'Não informado' }}</dd></div>
                    <div><dt>Tipo de acesso</dt><dd>{{ ucfirst($record->access_type) }}</dd></div>
                    <div><dt>{{ $record->requiresProperty() ? 'Imóvel' : 'Destino' }}</dt><dd>{{ $record->requiresProperty() ? $record->destination_property : $record->destination_label }}</dd></div>
                    <div><dt>Responsável</dt><dd>{{ $record->responsible_name ?? 'Não exige responsável de imóvel' }}</dd></div>
                    <div><dt>Período</dt><dd>{{ $record->periodLabel() }}</dd></div>
                </dl>
            </section>

            <section class="pre-cadastro-analise__bloco" aria-labelledby="analise-ocr-titulo">
                <header>
                    <h3 id="analise-ocr-titulo">Leitura automática do documento</h3>
                    @if ($leitura)
                        <small>{{ $record->documentTypeLabel() }} · lido em {{ $leitura->processed_at->format('d/m/Y \à\s H:i') }}</small>
                    @endif
                </header>

                @if (! $leitura)
                    <x-ui.empty-state title="Sem leitura automática" description="O documento não foi lido automaticamente. Confira os dados diretamente na imagem ou na portaria." />
                @elseif ($linhasDivergentes === [])
                    <p class="pre-cadastro-analise__nota">Os campos lidos conferem com os dados confirmados. A leitura é só apoio: não valida a autenticidade do documento e não aprova nada sozinha.</p>
                @else
                    <ul class="pre-cadastro-analise__divergencias" aria-label="Campos para conferir">
                        @foreach ($linhasDivergentes as $linha)
                            <li>
                                <div>
                                    <strong>{{ $linha['rotulo'] }}</strong>
                                    <x-ui.badge :variant="$linha['tom']">{{ $linha['situacao'] }}</x-ui.badge>
                                </div>
                                <dl>
                                    <div><dt>Lido</dt><dd>{{ $valorOcr($linha, 'extraido') }}</dd></div>
                                    <div><dt>Confiança</dt><dd>{{ $linha['confianca'] === null ? '—' : number_format($linha['confianca'] * 100, 0).'%' }}</dd></div>
                                    <div><dt>Confirmado</dt><dd>{{ $valorOcr($linha, 'confirmado') }}</dd></div>
                                    <div><dt>Atual</dt><dd>{{ $valorOcr($linha, 'atual') }}</dd></div>
                                </dl>
                            </li>
                        @endforeach
                    </ul>
                    <p class="pre-cadastro-analise__nota">A leitura é só apoio: não valida a autenticidade do documento e não aprova nada sozinha.</p>
                @endif
            </section>

            @if ($editing)
                <section class="pre-cadastro-analise__bloco" aria-labelledby="analise-edicao-titulo">
                    <header><h3 id="analise-edicao-titulo">Corrigir antes de aprovar</h3></header>
                    <x-ui.alert variant="warning" title="Edição auditada">
                        A versão enviada pelo solicitante será preservada. Informe a justificativa da correção.
                    </x-ui.alert>
                    <div class="registration-fields">
                        <x-ui.field id="edit-name" label="Nome completo" wire:model="editName" :error="$errors->first('editName')" required />
                        <x-ui.field id="edit-document" label="Documento" wire:model="editDocument" :error="$errors->first('editDocument')" required />
                        <x-ui.field id="edit-birth-date" type="date" label="Data de nascimento" wire:model="editBirthDate" :error="$errors->first('editBirthDate')" />
                        <x-ui.field id="edit-phone" label="Telefone" wire:model="editPhone" :error="$errors->first('editPhone')" />
                        <x-ui.field id="edit-email" type="email" label="E-mail" wire:model="editEmail" :error="$errors->first('editEmail')" />
                        <x-ui.field id="edit-address" label="Endereço informado" wire:model="editAddressInformed" :error="$errors->first('editAddressInformed')" />

                        @if ($record->access_type === 'turista')
                            <x-ui.field id="edit-destination" label="Destino" value="Praia do Santa Rita" readonly help="Turista não exige imóvel nem responsável." />
                        @elseif ($record->access_type === 'visitante')
                            <x-ui.select id="edit-destination-property" label="Imóvel de destino" wire:model="editDestinationProperty" :error="$errors->first('editDestinationProperty')" required>
                                @foreach (\App\Support\DestinationDirectory::options() as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </x-ui.select>
                        @else
                            <x-ui.field id="edit-destination-label" label="Destino" wire:model="editDestinationLabel" :error="$errors->first('editDestinationLabel')" required />
                            <x-ui.field id="edit-responsible" label="Responsável" wire:model="editResponsibleName" :error="$errors->first('editResponsibleName')" required />
                        @endif

                        <x-ui.field id="edit-period-start" type="datetime-local" label="Início do período" wire:model="editPeriodStart" :error="$errors->first('editPeriodStart')" required />
                        <x-ui.field id="edit-period-end" type="datetime-local" label="Término do período" wire:model="editPeriodEnd" :error="$errors->first('editPeriodEnd')" required />
                        <x-ui.field id="edit-vehicle-plate" label="Placa do veículo" wire:model="editVehiclePlate" :error="$errors->first('editVehiclePlate')" help="Deixe em branco se não houver veículo." />
                        <x-ui.field id="edit-vehicle-model" label="Modelo do veículo" wire:model="editVehicleModel" :error="$errors->first('editVehicleModel')" />
                        <x-ui.field id="edit-vehicle-color" label="Cor do veículo" wire:model="editVehicleColor" :error="$errors->first('editVehicleColor')" />
                        <x-ui.field id="edit-reason" label="Justificativa da correção" wire:model="editReason" help="Obrigatória para auditoria." :error="$errors->first('editReason')" required />
                    </div>
                    <div class="pre-registration-review-actions">
                        <x-ui.button variant="secondary" wire:click="cancelEdit">Cancelar edição</x-ui.button>
                        <x-ui.button variant="success" wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit">Salvar correção</x-ui.button>
                    </div>
                </section>
            @endif
        </div>
    </div>

    <div class="pre-cadastro-analise__secundario">
        <details class="pre-cadastro-analise__recolhido">
            <summary>Ver mais detalhes</summary>
            <dl class="pre-registration-detail__data">
                <div><dt>E-mail</dt><dd>{{ $record->email ?: 'Não informado' }}</dd></div>
                <div><dt>Endereço informado</dt><dd>{{ $record->address_informed ?: 'Não informado' }}</dd></div>
                <div><dt>Veículo</dt><dd>{{ $record->vehicleLabel() }}@if ($record->vehicle_model) · {{ $record->vehicle_model }}@endif @if ($record->vehicle_color) · {{ $record->vehicle_color }}@endif</dd></div>
                <div><dt>Tipo de documento</dt><dd>{{ $record->documentTypeLabel() }}</dd></div>
                <div><dt>Situação do documento</dt><dd>{{ $record->currentFileLink('documento')?->file ? $record->document_status : 'Não enviado' }}</dd></div>
                <div><dt>Situação da selfie</dt><dd>{{ $record->currentFileLink('selfie')?->file ? $record->selfie_status : 'Não enviada' }}</dd></div>
                @if ($leitura)
                    <div><dt>Leitura automática</dt><dd>{{ $leitura->engine }} · {{ $leitura->model_version }}</dd></div>
                @endif
                <div><dt>Protocolo</dt><dd>{{ $record->protocol }}</dd></div>
                <div><dt>Versão</dt><dd>{{ $record->version }}</dd></div>
                <div><dt>Enviado em</dt><dd>{{ $record->submitted_at->format('d/m/Y \à\s H:i') }}</dd></div>
            </dl>
        </details>

        @if ($leitura)
            <details class="pre-cadastro-analise__recolhido">
                <summary>Comparação completa da leitura</summary>
                <div class="pre-cadastro-analise__tabela">
                    <table>
                        <thead>
                            <tr><th>Campo</th><th>Extraído</th><th>Confiança</th><th>Confirmado</th><th>Atual</th><th>Situação</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($linhasOcr as $linha)
                                <tr>
                                    <th scope="row">{{ $linha['rotulo'] }}</th>
                                    <td data-label="Extraído">{{ $valorOcr($linha, 'extraido') }}</td>
                                    <td data-label="Confiança">{{ $linha['confianca'] === null ? '—' : number_format($linha['confianca'] * 100, 0).'%' }}</td>
                                    <td data-label="Confirmado">{{ $valorOcr($linha, 'confirmado') }}</td>
                                    <td data-label="Atual">{{ $valorOcr($linha, 'atual') }}</td>
                                    <td data-label="Situação"><x-ui.badge :variant="$linha['tom']">{{ $linha['situacao'] }}</x-ui.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        <details class="pre-cadastro-analise__recolhido">
            <summary>Histórico</summary>
            <ul class="pre-registration-history">
                <li><span></span><div><strong>Pré-cadastro enviado</strong><small>{{ $record->submitted_at->format('d/m/Y \à\s H:i') }} · Fluxo público</small></div></li>
                @if ($leitura)
                    <li><span></span><div><strong>Leitura automática confirmada pelo solicitante</strong><small>{{ $leitura->confirmed_at?->format('d/m/Y \à\s H:i') }}</small></div></li>
                @endif
                @foreach ($record->edits as $entry)
                    @if ($entry->action === 'situacao_alterada')
                        <li><span></span><div><strong>Situação alterada para "{{ $entry->new_value }}" por {{ $entry->operator_name }}</strong><small>{{ $entry->occurred_at->format('d/m/Y \à\s H:i') }} · {{ $entry->reason }}</small></div></li>
                    @elseif ($entry->action === 'revisao_sem_alteracao')
                        <li><span></span><div><strong>Dados revisados por {{ $entry->operator_name }}</strong><small>{{ $entry->occurred_at->format('d/m/Y \à\s H:i') }} · {{ $entry->reason }} · sem alteração de valor</small></div></li>
                    @else
                        <li><span></span><div><strong>{{ $entry->field }} corrigido por {{ $entry->operator_name }}</strong><small>{{ $entry->occurred_at->format('d/m/Y \à\s H:i') }} · {{ $entry->reason }}</small><small>{{ $entry->old_value }} → {{ $entry->new_value }}</small></div></li>
                    @endif
                @endforeach
            </ul>
        </details>
    </div>

    <section class="pre-cadastro-analise__decisao" aria-labelledby="analise-decisao-titulo">
        <header>
            <h3 id="analise-decisao-titulo">Decisão</h3>
            <small>Aprovar apenas prepara a solicitação para a Validação de Entrada. A entrada continua sendo decidida na portaria.</small>
        </header>

        @if ($record->status === 'aguardando')
            @if ($pendingDecision === 'corrigir' && $pode['corrigir'])
                <div class="pre-cadastro-analise__motivo">
                    <x-ui.select id="correction-item" label="Pedir correção de" wire:model="correctionItems" :error="$errors->first('correctionItems')">
                        @foreach (\App\Livewire\PreRegistrationReview::CORRECTION_ITEMS as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button variant="ghost" wire:click="cancelDecision">Cancelar</x-ui.button>
                    <x-ui.button variant="warning" wire:click="requestCorrection" wire:loading.attr="disabled">Confirmar solicitação de correção</x-ui.button>
                </div>
            @elseif ($pendingDecision === 'rejeitar' && $pode['rejeitar'])
                <div class="pre-cadastro-analise__motivo">
                    <x-ui.select id="rejection-reason" label="Motivo da rejeição" wire:model="rejectionReason" :error="$errors->first('rejectionReason')">
                        @foreach (\App\Livewire\PreRegistrationReview::REJECTION_REASONS as $valor => $rotulo)
                            <option value="{{ $valor }}">{{ $rotulo }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.button variant="ghost" wire:click="cancelDecision">Cancelar</x-ui.button>
                    <x-ui.button variant="danger" wire:click="reject" wire:loading.attr="disabled">Confirmar rejeição</x-ui.button>
                </div>
            @else
                <div class="pre-cadastro-analise__acoes">
                    @if ($pode['editar'] && ! $editing)
                        <x-ui.button variant="secondary" wire:click="beginEdit">Editar dados</x-ui.button>
                    @endif
                    @if ($pode['corrigir'])
                        <x-ui.button variant="secondary" wire:click="chooseDecision('corrigir')" :disabled="$editing">Solicitar correção</x-ui.button>
                    @endif
                    @if ($pode['rejeitar'])
                        <x-ui.button variant="danger" wire:click="chooseDecision('rejeitar')" :disabled="$editing">Rejeitar</x-ui.button>
                    @endif
                    @if ($pode['aprovar'])
                        <x-ui.button variant="success" wire:click="approve" wire:loading.attr="disabled" :disabled="$editing">Aprovar pré-cadastro</x-ui.button>
                    @endif
                    @unless ($podeDecidir)
                        <p class="pre-cadastro-analise__nota">Seu perfil permite consultar, mas não decidir este pré-cadastro.</p>
                    @endunless
                </div>
            @endif
        @else
            <x-ui.alert variant="info" title="Decisão já registrada">
                Consulte o histórico. Uma nova decisão exigirá um fluxo autorizado.
            </x-ui.alert>
        @endif
    </section>
</div>
