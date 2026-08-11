<div class="package-management">
    @if ($feedback)
        <x-ui.toast :variant="$feedback['variant']" :title="$feedback['title']" :dismissible="false">
            {{ $feedback['message'] }}
        </x-ui.toast>
    @endif

    @php
        $statusVariant = fn ($status) => match ($status) { 'entregue' => 'success', 'avisado' => 'info', default => 'warning' };
        $statusLabel = fn ($status) => match ($status) { 'entregue' => 'Entregue', 'avisado' => 'Avisado', default => 'Aguardando retirada' };
        $typeLabel = fn ($type) => match ($type) { 'envelope' => 'Envelope', 'volume' => 'Volume', 'outro' => 'Outro', default => 'Caixa' };
    @endphp

    @if ($mode === 'list')
        <section class="package-summary-grid" aria-label="Resumo das encomendas">
            <article><span>Aguardando retirada</span><strong>{{ $packageCounts['aguardando'] }}</strong><small>Ainda não avisadas</small></article>
            <article><span>Moradores avisados</span><strong>{{ $packageCounts['avisado'] }}</strong><small>Aguardando retirada</small></article>
            <article><span>Entregues</span><strong>{{ $packageCounts['entregue'] }}</strong><small>Ciclo concluído</small></article>
            <article><span>Total no filtro</span><strong>{{ count($filteredPackages) }}</strong><small>De {{ $totalPackages }} registradas</small></article>
        </section>

        <section class="package-list-card" aria-labelledby="package-list-title">
            <header>
                <div><h2 id="package-list-title">Encomendas</h2><p>Consulte o recebimento, armazenamento, aviso e entrega de pacotes.</p></div>
                <x-ui.button variant="primary" wire:click="createPackage">Registrar encomenda</x-ui.button>
            </header>

            <div class="package-list-filters">
                <label class="package-search">
                    <span class="sr-only">Buscar encomendas</span>
                    <x-icon name="search" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Buscar destinatário, imóvel, protocolo ou transportadora">
                </label>
                <x-ui.select id="package-status-filter" label="Situação" wire:model.live="statusFilter">
                    <option value="todas">Todas as situações</option>
                    <option value="aguardando">Aguardando retirada</option>
                    <option value="avisado">Avisado</option>
                    <option value="entregue">Entregue</option>
                </x-ui.select>
            </div>

            <x-ui.responsive-table
                label="Lista de encomendas"
                :state="count($filteredPackages) ? 'ready' : 'empty'"
                empty-title="Nenhuma encomenda encontrada"
                empty-description="Revise a busca ou selecione outra situação."
            >
                <x-slot:table>
                    <thead><tr><th>Destinatário</th><th>Imóvel</th><th>Transportadora</th><th>Local</th><th>Recebida em</th><th>Situação</th><th>Ações</th></tr></thead>
                    <tbody>
                        @foreach ($filteredPackages as $package)
                            <tr>
                                <td><strong>{{ $package['recipient'] }}</strong><small>{{ $package['protocol'] }}</small></td>
                                <td>{{ $package['property'] }}</td>
                                <td>{{ $package['carrier'] }}</td>
                                <td>{{ $package['storageLocation'] }}</td>
                                <td>{{ $package['receivedAt'] }}</td>
                                <td><x-ui.badge :variant="$statusVariant($package['status'])">{{ $statusLabel($package['status']) }}</x-ui.badge></td>
                                <td><x-ui.button variant="secondary" size="sm" wire:click="openPackage('{{ $package['id'] }}')">Detalhes</x-ui.button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:table>

                <x-slot:cards>
                    <ul class="ui-mobile-records">
                        @foreach ($filteredPackages as $package)
                            <li>
                                <div>
                                    <strong>{{ $package['recipient'] }}</strong>
                                    <small>{{ $package['property'] }} · {{ $package['carrier'] }}</small>
                                </div>
                                <time>{{ $package['receivedAt'] }}</time>
                                <x-ui.badge :variant="$statusVariant($package['status'])">{{ $statusLabel($package['status']) }}</x-ui.badge>
                                <x-ui.button variant="ghost" size="sm" wire:click="openPackage('{{ $package['id'] }}')">Detalhes</x-ui.button>
                            </li>
                        @endforeach
                    </ul>
                </x-slot:cards>
            </x-ui.responsive-table>

            <footer class="package-list-footer"><span>Exibindo {{ count($filteredPackages) }} de {{ $totalPackages }} encomendas</span></footer>
        </section>
    @elseif ($mode === 'detail' && $selectedPackage)
        <nav class="package-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Encomendas</button><x-icon name="chevron-right" /><span aria-current="page">{{ $selectedPackage['protocol'] }}</span></nav>

        <section class="package-detail-hero">
            <div><span>Encomenda</span><h2>{{ $selectedPackage['recipient'] }}</h2><p>{{ $selectedPackage['property'] }} · {{ $selectedPackage['carrier'] }} · {{ $typeLabel($selectedPackage['type']) }}</p></div>
            <div class="package-detail-hero__actions">
                <x-ui.badge :variant="$statusVariant($selectedPackage['status'])">{{ $statusLabel($selectedPackage['status']) }}</x-ui.badge>
            </div>
        </section>

        @if ($selectedPackage['notes'])
            <x-ui.alert variant="info" title="Observações">{{ $selectedPackage['notes'] }}</x-ui.alert>
        @endif

        <section class="package-detail-grid">
            <article class="package-section-card">
                <header><div><h3>Dados do recebimento</h3><p>Registrados no momento da chegada.</p></div></header>
                <dl class="package-detail-data">
                    <div><dt>Protocolo</dt><dd>{{ $selectedPackage['protocol'] }}</dd></div>
                    <div><dt>Local de armazenamento</dt><dd>{{ $selectedPackage['storageLocation'] }}</dd></div>
                    <div><dt>Recebida em</dt><dd>{{ $selectedPackage['receivedAt'] }}</dd></div>
                    <div><dt>Recebida por</dt><dd>{{ $selectedPackage['receivedBy'] }}</dd></div>
                </dl>
            </article>

            <article class="package-section-card">
                <header><div><h3>Aviso e entrega</h3><p>Ciclo até a retirada pelo morador ou responsável.</p></div></header>
                <dl class="package-detail-data">
                    <div><dt>Aviso enviado em</dt><dd>{{ $selectedPackage['notifiedAt'] ?? 'Ainda não avisado' }}</dd></div>
                    <div><dt>Entregue em</dt><dd>{{ $selectedPackage['deliveredAt'] ?? 'Ainda não entregue' }}</dd></div>
                    @if ($selectedPackage['deliveredTo'])
                        <div><dt>Retirado por</dt><dd>{{ $selectedPackage['deliveredTo'] }}</dd></div>
                    @endif
                </dl>

                @if ($selectedPackage['status'] === 'aguardando')
                    <div class="package-detail-actions">
                        <x-ui.button variant="secondary" wire:click="notifyRecipient">Avisar morador</x-ui.button>
                    </div>
                @endif

                @if ($selectedPackage['status'] !== 'entregue')
                    <div class="package-detail-actions">
                        <x-ui.modal id="deliver-package-modal" title="Registrar entrega" description="Confirme quem retirou esta encomenda." trigger-label="Registrar entrega" trigger-variant="success">
                            <div class="package-form-fields">
                                <x-ui.field id="delivered-to" label="Retirado por" wire:model="deliveredTo" placeholder="Nome de quem retirou" :error="$errors->first('deliveredTo')" required />
                            </div>
                            <label class="package-notes-field" for="delivery-notes">
                                <span>Observações da entrega</span>
                                <textarea id="delivery-notes" wire:model="deliveryNotes" maxlength="200" rows="3" placeholder="Ex.: retirado com documento de identificação"></textarea>
                            </label>
                            <x-slot:confirm><form method="dialog"><x-ui.button type="submit" variant="success" wire:click="deliverPackage">Confirmar entrega</x-ui.button></form></x-slot:confirm>
                        </x-ui.modal>
                    </div>
                @endif
            </article>
        </section>
    @else
        <nav class="package-breadcrumb" aria-label="Caminho da página"><button type="button" wire:click="backToList">Encomendas</button><x-icon name="chevron-right" /><span aria-current="page">Nova encomenda</span></nav>

        <section class="package-form-layout">
            <form class="package-form-card" wire:submit="savePackage">
                <header><div><h2>Registrar encomenda</h2><p>O morador ainda não será avisado automaticamente.</p></div></header>

                <fieldset>
                    <legend>Recebimento</legend>
                    <div class="package-form-fields">
                        <x-ui.field id="package-recipient" label="Destinatário" wire:model="recipientName" :error="$errors->first('recipientName')" required />
                        <x-ui.select id="package-property" label="Imóvel" wire:model="property" :error="$errors->first('property')" required>
                            <option value="">Selecione o imóvel</option>
                            @foreach ($imoveis as $imovel)
                                <option value="{{ $imovel->codigo }}">{{ $imovel->label() }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.field id="package-carrier" label="Transportadora" wire:model="carrier" placeholder="Ex.: Correios, Mercado Livre" :error="$errors->first('carrier')" required />
                        <x-ui.select id="package-type" label="Tipo" wire:model="type" :error="$errors->first('type')" required>
                            <option value="caixa">Caixa</option>
                            <option value="envelope">Envelope</option>
                            <option value="volume">Volume</option>
                            <option value="outro">Outro</option>
                        </x-ui.select>
                        <x-ui.field id="package-storage" label="Local de armazenamento" wire:model="storageLocation" placeholder="Ex.: Prateleira A3" :error="$errors->first('storageLocation')" required />
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Observações</legend>
                    <label class="package-notes-field" for="package-notes">
                        <span>Observações</span>
                        <textarea id="package-notes" wire:model="notes" maxlength="200" rows="3" placeholder="Ex.: volume frágil, requer duas pessoas…"></textarea>
                        <small>{{ mb_strlen($notes) }}/200 caracteres</small>
                    </label>
                </fieldset>

                <footer><x-ui.button variant="secondary" wire:click="backToList">Cancelar</x-ui.button><x-ui.button type="submit" variant="success">Registrar encomenda</x-ui.button></footer>
            </form>

            <aside class="package-form-context">
                <x-ui.card title="O que acontece ao registrar?" description="O ciclo segue em etapas separadas">
                    <ul>
                        <li><x-icon name="package" /><span><strong>Recebimento</strong><small>A encomenda entra como "Aguardando retirada".</small></span></li>
                        <li><x-icon name="bell" /><span><strong>Aviso</strong><small>Precisa ser registrado manualmente na tela de detalhe.</small></span></li>
                        <li><x-icon name="check-circle" /><span><strong>Entrega</strong><small>Só é concluída com o nome de quem retirou.</small></span></li>
                    </ul>
                </x-ui.card>
                <x-ui.alert variant="info" title="Registro de recebimento">O aviso ao morador e a entrega são registrados em etapas separadas, na tela de detalhe.</x-ui.alert>
            </aside>
        </section>
    @endif
</div>
