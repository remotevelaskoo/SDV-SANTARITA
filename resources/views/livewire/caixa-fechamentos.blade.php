<div class="caixa-fechamentos">
    <section class="cash-register-history-card" aria-labelledby="fechamentos-titulo">
        <header>
            <div>
                <h2 id="fechamentos-titulo">{{ $consolidado ? 'Fechamentos da implantação' : 'Meus fechamentos' }}</h2>
                <p>Fechamentos não são editados nem excluídos. Abra um para ver o detalhamento, imprimir ou exportar.</p>
            </div>
            @if ($consolidado)
                <x-ui.select id="fechamentos-operador" label="Operador" wire:model.live="operador">
                    <option value="">Todos</option>
                    @foreach ($operadores as $item)
                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                    @endforeach
                </x-ui.select>
            @endif
        </header>

        <x-ui.responsive-table
            label="Fechamentos de caixa"
            :state="$fechamentos->isEmpty() ? 'empty' : 'ready'"
            empty-title="Nenhum fechamento encontrado"
            empty-description="Os caixas fechados aparecerão aqui."
        >
            <x-slot:table>
                <thead><tr><th>Fechamento</th><th>Operador</th><th>Posto</th><th>Período</th><th class="numeric">Esperado</th><th class="numeric">Contado</th><th class="numeric">Diferença</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr></thead>
                <tbody>
                    @foreach ($fechamentos as $item)
                        <tr>
                            <td>{{ $item->numero_fechamento ?? '—' }}</td>
                            <td>{{ $item->operator?->name ?? 'Sistema' }}</td>
                            <td>{{ $item->posto?->nome ?? $item->terminal }}</td>
                            <td>{{ $item->opened_at->format('d/m/Y H:i') }} às {{ $item->closed_at?->format('H:i') }}</td>
                            <td class="numeric">R$ {{ number_format((float) $item->expected_amount, 2, ',', '.') }}</td>
                            <td class="numeric">R$ {{ number_format((float) $item->informed_amount, 2, ',', '.') }}</td>
                            <td class="numeric">R$ {{ number_format((float) $item->difference, 2, ',', '.') }}</td>
                            <td><x-ui.badge :variant="$item->closing_status === 'conferido' ? 'success' : 'warning'">{{ $item->closing_status === 'conferido' ? 'Conferido' : 'Diferença' }}</x-ui.badge></td>
                            <td><a href="{{ route('cash-register.closing', $item->id) }}">Ver fechamento</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-slot:table>

            <x-slot:cards>
                <ul class="ui-mobile-records">
                    @foreach ($fechamentos as $item)
                        <li>
                            <div>
                                <strong>{{ $item->numero_fechamento ?? '—' }}</strong>
                                <small>{{ $item->operator?->name ?? 'Sistema' }} · {{ $item->opened_at->format('d/m/Y H:i') }}</small>
                            </div>
                            <x-ui.badge :variant="$item->closing_status === 'conferido' ? 'success' : 'warning'">R$ {{ number_format((float) $item->difference, 2, ',', '.') }}</x-ui.badge>
                            <a href="{{ route('cash-register.closing', $item->id) }}">Ver fechamento</a>
                        </li>
                    @endforeach
                </ul>
            </x-slot:cards>
        </x-ui.responsive-table>
    </section>
</div>
