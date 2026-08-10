@props(['amount' => '15,00', 'cashbox' => 'Caixa Portaria Principal'])

<section x-data="{ choice: 'yes' }" {{ $attributes->class('ui-contribution') }}>
    <header><div><h3>Contribuição / taxa de acesso</h3><p>A contribuição não determina a autorização da entrada.</p></div><x-ui.badge variant="info">{{ $cashbox }}</x-ui.badge></header>
    <div class="ui-contribution__choices">
        <label><input type="radio" name="contribution" value="yes" x-model="choice"><span><strong>Contribui</strong><small>Registrar pagamento</small></span></label>
        <label><input type="radio" name="contribution" value="no" x-model="choice"><span><strong>Não contribui</strong><small>Sem pagamento</small></span></label>
        <label><input type="radio" name="contribution" value="exempt" x-model="choice"><span><strong>Isento</strong><small>Isenção registrada</small></span></label>
    </div>
    <div class="ui-contribution__details" x-show="choice === 'yes'">
        <x-ui.field id="contribution-value" label="Valor" :value="'R$ '.$amount" />
        <x-ui.select id="contribution-method" label="Forma de pagamento"><option>Dinheiro</option><option>PIX</option><option>Cartão</option></x-ui.select>
        <x-ui.field id="contribution-payer" label="Recebido de" value="Marcos Vinicius da Silva" />
        <aside><span>Resumo</span><dl><div><dt>Valor</dt><dd>R$ {{ $amount }}</dd></div><div><dt>Total</dt><dd>R$ {{ $amount }}</dd></div></dl></aside>
    </div>
</section>
