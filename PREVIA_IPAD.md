# Abrir o SDV One funcionando no iPad (sem Windows)

Esta branch adiciona uma prévia **privada** no GitHub Codespaces. Não é produção e não usa dados reais.

## No Safari do iPad

1. Abra: https://github.com/remotevelaskoo/SDV-SANTARITA/tree/preview/ipad-sdv-one
2. Entre na sua conta do GitHub.
3. Toque em **Code** → **Codespaces** → **Create codespace on preview/ipad-sdv-one**.
   - Se não aparecer, confirme que está nesta branch e que sua conta tem permissão para Codespaces.
4. No primeiro início, o GitHub configura PHP 8.4, Node 24, o banco de demonstração e compila o frontend automaticamente. Confira a aba **Terminal** até ver `[SDV One] Ambiente pronto`.
5. Abra a aba **PORTS / Portas** e toque no endereço da porta **8000**, que deve continuar **Private / Privada**.
6. Faça login com o usuário de demonstração `administrador` e a senha `sdv2026`. Também é possível testar com `portaria`, `gestor` ou `portaria.leitura`.

Se a porta não abrir, no Terminal rode:
```bash
bash .devcontainer/start-preview.sh
```
Se o primeiro preparo falhar, confira o log no Terminal e execute:
```bash
bash .devcontainer/setup-preview.sh
bash .devcontainer/start-preview.sh
```

## Segurança

- A URL encaminhada pelo Codespaces deve permanecer **privada**. Não defina a porta como pública.
- Usa **SQLite descartável local ao Codespace** e credenciais de demonstração; não se conecta ao condomínio.
- Integrações externas, relé, terminal facial e OCR real continuam desligados na configuração padrão.
- A prévia não fica disponível 24 horas: o Codespace pode entrar em suspensão por inatividade.
- Custos e cotas do Codespaces dependem da conta GitHub.
- Branch de backup: `backup/sdv-access-antes-sdv-one-20261108`.
- Branch de design para revisão: `design/sdv-one-interface-v1`. Esta branch de prévia inclui apenas a infraestrutura de execução + correção já testada do `PostoSeeder`.
