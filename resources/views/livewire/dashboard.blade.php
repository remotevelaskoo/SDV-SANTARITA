<div class="dashboard">
    <section class="welcome" aria-labelledby="welcome-title">
        <h2 id="welcome-title">Olá, Tatiane</h2>
        <p>Situação atual do condomínio Santa Rita.</p>
    </section>

    <section class="alerts" aria-label="Alertas críticos">
        @foreach ($alerts as $alert)
            <article class="alert alert--{{ $alert['severity'] }}">
                <x-icon name="alert" />
                <div>
                    <h3>{{ $alert['title'] }}</h3>
                    <p>{{ $alert['description'] }}</p>
                </div>
            </article>
        @endforeach
    </section>

    <section aria-label="Indicadores">
        <div class="metrics-grid">
            @foreach ($metrics as $metric)
                <article class="metric-card">
                    <div class="metric-card__heading">
                        <h3>{{ $metric['label'] }}</h3>
                        @if ($metric['link'])
                            <x-icon name="arrow-up-right" />
                        @endif
                    </div>

                    <strong class="metric-card__value">
                        @if ($metric['type'] === 'currency')
                            R$ {{ number_format($metric['value'], 2, ',', '.') }}
                        @else
                            {{ number_format($metric['value'], 0, ',', '.') }}
                        @endif
                    </strong>

                    <p class="metric-card__comparison metric-card__comparison--{{ $metric['trend'] }}">
                        <x-icon :name="$metric['trend'] === 'up' ? 'arrow-up-right' : ($metric['trend'] === 'down' ? 'arrow-down-right' : 'minus')" />
                        <strong>{{ number_format($metric['variation'], 1, ',', '.') }}%</strong>
                        <span>{{ $metric['comparison'] }}</span>
                    </p>

                    <footer>
                        <span>{{ $metric['period'] }}</span>
                        <span><x-icon name="refresh" /> {{ $metric['updated'] }}</span>
                    </footer>
                </article>
            @endforeach
        </div>
    </section>

    <section class="dashboard-panels" aria-label="Resumo operacional">
        <article class="panel chart-panel">
            <header class="panel__header">
                <div>
                    <h2>Entradas e saídas</h2>
                    <p>Volume de movimentações validadas no período</p>
                </div>
                <div class="period-selector" role="group" aria-label="Período do gráfico">
                    @foreach (['hoje' => 'Hoje', '7dias' => '7 dias', '30dias' => '30 dias'] as $value => $label)
                        <button
                            type="button"
                            wire:click="setPeriod('{{ $value }}')"
                            @class(['is-active' => $period === $value])
                            aria-pressed="{{ $period === $value ? 'true' : 'false' }}"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </header>

            @php
                $chartData = $series[$period];
                $chartMaximum = max(array_merge(array_column($chartData, 'entries'), array_column($chartData, 'exits'))) ?: 1;
                $chartWidth = 720;
                $chartHeight = 230;
                $chartTop = 18;
                $chartBottom = 36;
                $chartPlotHeight = $chartHeight - $chartTop - $chartBottom;
                $chartStep = count($chartData) > 1 ? $chartWidth / (count($chartData) - 1) : 0;
                $entryPoints = [];
                $exitPoints = [];
                foreach ($chartData as $index => $point) {
                    $x = round($index * $chartStep, 2);
                    $entryY = round($chartTop + $chartPlotHeight - (($point['entries'] / $chartMaximum) * $chartPlotHeight), 2);
                    $exitY = round($chartTop + $chartPlotHeight - (($point['exits'] / $chartMaximum) * $chartPlotHeight), 2);
                    $entryPoints[] = $x.','.$entryY;
                    $exitPoints[] = $x.','.$exitY;
                }
            @endphp

            <div class="chart" wire:key="chart-{{ $period }}">
                <div class="chart__legend" aria-hidden="true">
                    <span><i class="legend-dot legend-dot--entries"></i>Entradas</span>
                    <span><i class="legend-dot legend-dot--exits"></i>Saídas</span>
                </div>
                <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="Gráfico de entradas e saídas no período {{ $period }}">
                    <defs>
                        <linearGradient id="entries-gradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="var(--blue-600)" stop-opacity=".28" />
                            <stop offset="1" stop-color="var(--blue-600)" stop-opacity=".01" />
                        </linearGradient>
                        <linearGradient id="exits-gradient" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="var(--cyan-400)" stop-opacity=".24" />
                            <stop offset="1" stop-color="var(--cyan-400)" stop-opacity=".01" />
                        </linearGradient>
                    </defs>
                    @for ($line = 0; $line < 5; $line++)
                        <line class="chart__grid" x1="0" y1="{{ $chartTop + ($chartPlotHeight / 4 * $line) }}" x2="{{ $chartWidth }}" y2="{{ $chartTop + ($chartPlotHeight / 4 * $line) }}" />
                    @endfor
                    <polygon class="chart__area chart__area--entries" points="0,{{ $chartTop + $chartPlotHeight }} {{ implode(' ', $entryPoints) }} {{ $chartWidth }},{{ $chartTop + $chartPlotHeight }}" />
                    <polygon class="chart__area chart__area--exits" points="0,{{ $chartTop + $chartPlotHeight }} {{ implode(' ', $exitPoints) }} {{ $chartWidth }},{{ $chartTop + $chartPlotHeight }}" />
                    <polyline class="chart__line chart__line--entries" points="{{ implode(' ', $entryPoints) }}" />
                    <polyline class="chart__line chart__line--exits" points="{{ implode(' ', $exitPoints) }}" />
                    @foreach ($chartData as $index => $point)
                        <text class="chart__label" x="{{ $index * $chartStep }}" y="{{ $chartHeight - 8 }}" text-anchor="middle">{{ $point['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        </article>

        <article class="panel accesses-panel">
            <header class="panel__header">
                <div>
                    <h2>Acessos recentes</h2>
                    <p>Últimas movimentações validadas</p>
                </div>
            </header>

            <div class="access-table-wrap">
                <table class="access-table">
                    <caption class="sr-only">Últimos acessos validados</caption>
                    <thead><tr><th>Hora</th><th>Pessoa</th><th>Vínculo</th><th>Imóvel</th><th>Ponto de acesso</th><th>Tipo</th><th>Resultado</th></tr></thead>
                    <tbody>
                        @foreach ($accesses as $access)
                            <tr>
                                <td class="numeric">{{ $access['time'] }}</td>
                                <td><strong>{{ $access['name'] }}</strong><small>{{ $access['document'] }}</small></td>
                                <td>{{ $access['relation'] }}</td>
                                <td>{{ $access['property'] }}</td>
                                <td>{{ $access['point'] }}@if ($access['plate'])<small>🚙 {{ $access['plate'] }}</small>@endif</td>
                                <td><span class="access-type access-type--{{ $access['type'] }}"><x-icon :name="$access['type'] === 'entrada' ? 'arrow-down-left' : 'arrow-up-right'" />{{ $access['type'] === 'entrada' ? 'Entrada' : 'Saída' }}</span></td>
                                <td><span class="result-badge result-badge--{{ $access['result'] }}">{{ ucfirst($access['result']) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <ul class="access-cards">
                @foreach ($accesses as $access)
                    <li>
                        <div><strong>{{ $access['name'] }}</strong><small>{{ $access['document'] }} · {{ $access['relation'] }}</small></div>
                        <time>{{ $access['time'] }}</time>
                        <p>{{ $access['property'] }}<small>{{ $access['point'] }}</small></p>
                        <span class="result-badge result-badge--{{ $access['result'] }}">{{ ucfirst($access['result']) }}</span>
                    </li>
                @endforeach
            </ul>
        </article>
    </section>

    <section class="panel cameras-panel" aria-labelledby="cameras-title">
        <header class="panel__header">
            <div><h2 id="cameras-title">Monitoramento de Câmeras</h2><p>Visualização em tempo real dos pontos críticos de acesso</p></div>
            <span class="live-badge"><i></i>AO VIVO</span>
        </header>
        <div class="cameras-grid">
            @foreach ($cameras as $camera)
                <article @class(['camera', 'camera--offline' => ! $cameraStatus[$camera['id']]]) wire:key="{{ $camera['id'] }}">
                    @if ($cameraStatus[$camera['id']])
                        <span class="camera__record"><i></i> REC {{ strtoupper($camera['id']) }}</span>
                        <x-icon name="video" class="camera__placeholder" />
                        <h3>{{ $camera['title'] }}</h3>
                    @else
                        <x-icon name="alert" class="camera__placeholder" />
                        <strong>SEM SINAL</strong>
                    @endif
                    <button type="button" wire:click="toggleCamera('{{ $camera['id'] }}')" aria-label="{{ $cameraStatus[$camera['id']] ? 'Desligar' : 'Ligar' }} {{ $camera['title'] }}">
                        <x-icon name="power" />
                    </button>
                </article>
            @endforeach
        </div>
    </section>
</div>
