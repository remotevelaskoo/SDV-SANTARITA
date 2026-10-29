<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Fechamento {{ $turno->numero_fechamento }} — SDV Access Santa Rita</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="fechamento-impressao">
        <div class="fechamento-impressao__barra">
            <a href="{{ route('cash-register.closing', $turno->id) }}">← Voltar ao resumo</a>
            <button type="button" onclick="window.print()">Imprimir fechamento</button>
        </div>
        <main class="fechamento-impressao__folha">
            <p class="fechamento-impressao__marca">SDV Access · Santa Rita</p>
            @include('caixa.fechamento-relatorio')
            <div class="fechamento-impressao__assinaturas">
                <span>Operador responsável</span>
                <span>Conferente</span>
            </div>
        </main>
    </body>
</html>
