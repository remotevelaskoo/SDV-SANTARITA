<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Pré-cadastro — divulgação · SDV One</title>
    <style>
        :root { --azul: #12305a; --texto: #1d2733; --suave: #5b6878; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: var(--texto); background: #f2f5f9; }
        .barra { display: flex; gap: .75rem; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; background: #fff; border-bottom: 1px solid #dde3ea; }
        .barra a, .barra button { font: inherit; font-weight: 600; padding: .6rem 1rem; border-radius: .5rem; border: 1px solid #c7d0db; background: #fff; color: var(--azul); text-decoration: none; cursor: pointer; }
        .barra button { background: var(--azul); color: #fff; border-color: var(--azul); }
        .cartaz { max-width: 640px; margin: 1.5rem auto; padding: 2rem 1.5rem; background: #fff; border-radius: 1rem; text-align: center; box-shadow: 0 2px 10px rgba(18, 48, 90, .08); }
        .cartaz small { letter-spacing: .12em; text-transform: uppercase; font-weight: 700; color: var(--suave); }
        .cartaz h1 { margin: .5rem 0; font-size: 2rem; color: var(--azul); }
        .cartaz p { margin: .5rem 0; font-size: 1.1rem; line-height: 1.45; }
        .qr { width: min(320px, 80vw); margin: 1.25rem auto; }
        .qr svg { width: 100%; height: auto; display: block; }
        .passos { text-align: left; max-width: 26rem; margin: 1rem auto; padding-left: 1.25rem; font-size: 1.05rem; line-height: 1.6; }
        .aviso { color: var(--suave); font-size: .95rem; }
        .link { margin-top: 1rem; padding: .75rem; border: 1px dashed #c7d0db; border-radius: .5rem; word-break: break-all; font-family: ui-monospace, monospace; font-size: .85rem; text-align: left; }
        .config { max-width: 640px; margin: 1.5rem auto; padding: 1.25rem; background: #fff7e0; border: 1px solid #f0d58a; border-radius: .75rem; line-height: 1.5; }
        @media print {
            body { background: #fff; }
            .barra, .link, .so-tela { display: none !important; }
            .cartaz { box-shadow: none; margin: 0 auto; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <a href="{{ route('pre-registrations') }}">← Voltar aos pré-cadastros</a>
        @if ($url)
            <button type="button" onclick="window.print()">Imprimir cartaz</button>
        @endif
    </div>

    @if ($url)
        <main class="cartaz">
            <small>Condomínio Santa Rita</small>
            <h1>Faça seu pré-cadastro antes de chegar</h1>
            <p>Evite fila na portaria: aponte a câmera do celular para o código abaixo.</p>
            <div class="qr" role="img" aria-label="QR code do pré-cadastro">{!! $qr !!}</div>
            <ol class="passos">
                <li>Preencha seus dados e a data prevista da visita.</li>
                <li>Fotografe o documento e o seu rosto.</li>
                <li>Guarde o link de acompanhamento que aparece no final.</li>
            </ol>
            <p class="aviso">O pré-cadastro não libera a entrada sozinho: a portaria confere tudo na chegada.</p>
            <div class="link so-tela">
                <strong>Link para compartilhar (WhatsApp, e-mail, site):</strong><br>
                <span id="link-publico">{{ $url }}</span>
            </div>
            <p class="aviso so-tela">Se o link for usado de forma indevida, troque o código do convite no portal e atualize <code>SDV_PRE_CADASTRO_LINK_PUBLICO</code>; o link e o QR antigos deixam de funcionar.</p>
        </main>
    @else
        <main class="config" role="status">
            <strong>Link do pré-cadastro ainda não configurado.</strong>
            <p>Peça ao administrador do servidor para definir <code>SDV_PRE_CADASTRO_LINK_PUBLICO</code> no arquivo <code>.env</code>, no formato <code>https://endereço-do-portal/#convite=CÓDIGO</code> (o mesmo código configurado no portal). Depois disso, esta página mostra o QR code para imprimir.</p>
        </main>
    @endif
</body>
</html>
