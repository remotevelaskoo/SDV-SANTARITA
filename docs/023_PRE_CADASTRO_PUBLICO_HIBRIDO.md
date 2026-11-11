# SDV One — pré-cadastro público integrado ao nó local

**Data:** 11/11/2026  
**Base verificada:** main `fc0497a`, atualizada antes da implementação.  
**Referências:** documentos 006, 019, 021 (inclusive revisão do PO de 08/11/2026) e ADR-018.  
**Situação:** código e banco isolado preparados; publicação e conexão com o equipamento real ainda não concluídas. Não afirmar operação híbrida homologada.

## 1. Decisão e escopo

O PO descartou a prévia do sistema completo no Vercel e solicitou um processo público de pré-cadastro integrado ao sistema. Manter o núcleo administrativo e operacional no servidor local, em Laravel/Blade/Livewire. O serviço separado `services/pre-cadastro-publico` usa HTML/CSS/JavaScript e pequenas Functions Node.js, compatíveis com Vercel; não migra o produto para Next.js/React.

O sistema legado de certificados é independente e não recebe alterações. Não publicar a raiz Laravel como uma nova demonstração completa do sistema.

## 2. Infraestrutura conferida em 11/11/2026

| Recurso | Situação real |
|---|---|
| Organização Supabase | `remotevelaskoo`, escolhida pelo usuário |
| Novo projeto isolado | `sdv-santa-rita` (`tbcxutrdstdiymgtzhhm`), região `sa-east-1` |
| Custo de criação cotado pelo Supabase | US$ 0/mês; não é promessa de gratuidade além dos limites do plano |
| Projeto `certificados` | Mantido; nenhuma migração executada nele |
| Banco do portal | Duas tabelas, políticas explícitas de bloqueio ao acesso direto e funções de quota/ACK |
| Imagens | Bucket `pre-cadastro-privado`, não público, limite 8 MB por objeto |
| Segurança do banco | RLS e ausência de SELECT para anon/authenticated conferidos por SQL; auditor Supabase sem avisos após políticas explícitas |
| Dados no banco | Nenhuma solicitação real cadastrada |
| Teste antigo Vercel | `sdv-santarita-vercel`; exclusão ainda exige confirmação do usuário no painel, conforme ferramenta do provedor |
| Novo projeto Vercel do portal | `sdv-santa-rita-pre-cadastro` (`prj_UuOLXXPCxRdHJzxiUx0W3FYMumQ8`), raiz `services/pre-cadastro-publico`, pré-configurado e desativado |
| Novo portal publicado | Ainda não; chave secreta de backend e configuração operacional pendentes |

Migrações remotas aplicadas: `pre_cadastro_publico_hibrido` e `pre_cadastro_politicas_privadas_explicitas`. `services/pre-cadastro-publico/supabase/bootstrap.sql` reproduz a estrutura final em um projeto novo. Não reaplicar esse bootstrap sobre o banco já criado; alterações futuras exigem migrações próprias.

## 3. Fluxo

1. Usuário abre link/QR de convite do portal.
2. Preenche identificação e dados opcionais; envia frente documental, verso de RG/CIN e selfie em arquivos separados.
3. Backend valida convite, dados e imagens. Imagens sobem diretamente por URL assinada, evitando o limite de corpo das Functions Vercel.
4. Somente depois de todos os arquivos validados a solicitação sai de `rascunho` para `enviado`. O visitante recebe protocolo e recibo privado de acompanhamento.
5. O comando Laravel `pre-cadastro:sincronizar-nuvem` busca até dez solicitações por ciclo, por conexão HTTPS de saída. Não abre porta no servidor do condomínio.
6. O nó confere implantação, campos, categorias e checksums; grava o `PreRegistration` existente e os arquivos via `PrivateFileService`. O registro aparece na fila já usada pela portaria, com situação `aguardando` e conferência manual obrigatória.
7. Confirmação à nuvem só acontece após o commit local. Reentrega usa o vínculo único da solicitação externa e não recria registros ou arquivos.
8. Decisões locais posteriores retornam à nuvem por ACK com versão crescente. Versões antigas ou divergentes são recusadas, sem sobrescrever silenciosamente a decisão.

## 4. Campos e interface

O formulário público separado tem blocos de identificação, documento, selfie, veículo e confirmação, com identidade SDV One, campos legíveis e uma coluna no celular. Não possui menus administrativos, microfone, caixa ou acesso a terminais.

- Nome e CPF obrigatórios; telefone, e-mail, nascimento e endereço opcionais, validados quando informados.
- Tipos públicos preservados: turista, visitante e prestador. Não confundir com todos os tipos do cadastro rápido.
- **Revisão atual do PO:** visitante tem imóvel e responsável obrigatórios. O portal valida o imóvel no catálogo real e resolve o responsável no servidor; nomes de responsáveis não aparecem na lista pública. Não aceitar o responsável indicado pelo navegador como autoridade.
- Turista não possui vínculo com imóvel nem responsável; destino Praia do Santa Rita. Prestador mantém destino no formulário público, sem responsável por esta regra.
- Catálogo real vem de `PORTAL_DESTINATIONS`, uma lista de objetos `label`/`responsible`. Não copiar apartamentos ou pessoas do `DestinationDirectory` de demonstração. Sem catálogo, não aceitar visitante/prestador com destino inventado.
- Frente documental e selfie obrigatórias; verso adicional obrigatório no RG/CIN. Fotografias válidas, com lado mínimo de 320 px e limite de 40 milhões de pixels. Metadados EXIF removidos na normalização JPEG; objetos originais também são privados.
- Veículo opcional; placa obrigatória se indicado veículo. Modelo e cor opcionais.
- Nome digitado manualmente. OCR automático ainda não exposto por esta primeira fatia na nuvem; o formulário informa conferência manual. O motor e o formulário Laravel atuais não foram removidos ou alterados.
- Recibo informa recebimento/situação e não apresenta dados pessoais ou documentos. Aprovação de pré-cadastro não equivale à autorização de entrada.

## 5. Limites de autoridade e privacidade

O portal não cria pessoas operacionais, vínculos, autorização, credencial, contribuição ou comando físico. Caixa e contribuição de turista de R$ 25,00 permanecem exclusivamente nas regras atuais do atendimento local. O envio facial conserva permissões, vigência, decisão humana e homologação existentes.

Supabase é acessado pelo backend com chave secreta; essa chave não pode aparecer no frontend, repositório ou nó local. O nó usa segredo próprio e revogável. O convite também tem segredo diferente. As tabelas não concedem leitura/escrita a `anon`/`authenticated` e o bucket não tem política pública. Cabeçalhos proíbem cache e referer; sem analytics de terceiros.

Antes da produção: formalizar aviso e versão de privacidade, retenção dos rascunhos/objetos originais, exclusão de órfãos e limpeza dos contadores antigos. Backup externo e restauração continuam independentes da sincronização. O projeto não implementa sincronização de caixa, acessos ou todos os cadastros, nem homologa equipamentos offline.

## 6. Queda e recuperação

- Falha da internet do condomínio: portal continua recebendo na nuvem; os itens aguardam busca, sem aprovação automática.
- Falha da nuvem: operações já existentes no nó local continuam conforme as regras atuais; esse conector não bloqueia o cadastro rápido.
- Falha de upload: solicitação fica como rascunho, fora da fila local; reenvio não deve sobrescrever objetos já aceitos.
- Falha de ACK após commit: o pré-cadastro e a foto permanecem locais; o comando confirma na próxima execução sem duplicá-los.
- Campos mudados e solicitações inválidas: não sobrescrever fatos locais; retorno de versão conflitante fica como pendência.
- Comando retorna contagens e código de falha sem payload sensível. Painel dedicado de conectividade, alertas de atraso e demais metas do ADR-018 ainda são pendências antes do go-live.

## 7. Configuração e ativação

1. Revisar/integração da branch antes de atualizar o nó local. Executar sua migração, que só acrescenta `pre_cadastro_nuvem_recebimentos`; não move nem apaga tabelas existentes.
2. Configurar projeto Vercel separado com raiz `services/pre-cadastro-publico`. Usar Node.js, saída `public` e Functions `api/`, nunca a raiz administrativa.
3. Configurar no backend as variáveis do `.env.example` do portal: URL Supabase, chave secreta, UUID da implantação operacional, segredos independentes do convite/nó/quota, nome, catálogo real e aviso de privacidade. Começar com `PORTAL_ENABLED=false`.
4. O UUID deve corresponder à implantação do nó local, e não ao ID do projeto Supabase. Não inventar/copiar UUID de dados demo.
5. As ferramentas Supabase conectadas fornecem as chaves publicáveis, mas não a chave secreta. O proprietário deve configurá-la diretamente como variável protegida do projeto Vercel; não enviar a chave pelo chat ou colocá-la no código. Sem ela não habilitar o endpoint nem prometer publicação funcional.
6. No nó local configurar `SDV_PRE_CADASTRO_NUVEM_URL`, `SDV_PRE_CADASTRO_NUVEM_TOKEN` (segredo exclusivo do nó) e `SDV_PRE_CADASTRO_NUVEM_IMPLANTACAO_ID`; habilitar só no ambiente de teste. Ativar o scheduler Laravel com `schedule:run`.
7. Homologar envio, arquivos privados, importação na fila, decisão, reenvio e queda/retorno usando dados sintéticos. Só então liberar o convite aos usuários reais.

A exclusão do projeto Vercel antigo exige ação explícita no painel, pelo link devolvido pelo provedor. Ela não é condição para testar a nova integração, e não deve atingir `certificados` nem o repositório principal.

## 8. Validação executada

- `php artisan test`: **571 testes passaram**, incluindo oito novos de importação/retentativa, isolamento, integridade, verso obrigatório, status, visitante com responsável e configuração desativada. Executado após build do Vite.
- `pnpm build`: aprovado; `pnpm test:js`: **22 testes passaram**.
- `vendor/bin/pint --test`: aprovado.
- `npm test` no portal: **10 testes passaram**, com imagens sintéticas, transporte simulado e PostgreSQL PGlite para esquema, RLS, concessões, quota e ACK.
- SQL no Supabase novo: RLS ligado, privilégios públicos negados, bucket privado e zero solicitações. Auditor de segurança sem avisos após políticas explícitas.
- Revisão visual/interação de navegador: tentativa de execução impedida pelas restrições do ambiente de Chromium; **não homologada**. Sem promessa de teste em computador/celular real.
- Não executados: upload real ao Supabase via portal publicado, importação no servidor físico do condomínio, OCR remoto, aprovação real ou envio à facial.

**Próximo marco:** configuração dos segredos e da implantação real, publicação restrita de homologação e teste integrado. Esta entrega não deve ser marcada como produção pronta.
