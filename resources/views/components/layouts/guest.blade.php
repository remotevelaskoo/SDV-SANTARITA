<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Acesso ao sistema de controle de acesso do condomínio Santa Rita.">

        <title>{{ $title ?? config('app.name') }} — SDV Access Santa Rita</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="auth-body">
        <a class="skip-link" href="#conteudo-principal">Pular para o conteúdo</a>

        <main id="conteudo-principal" tabindex="-1">
            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>
