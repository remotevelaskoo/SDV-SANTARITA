-- Painel de gestão fora do condomínio (PO 15/11/2026). Aplicar só no projeto isolado
-- sdv-santa-rita, antes de publicar api/gestao.js. Só totais do dia e o nome do operador
-- de cada caixa: nenhum nome, documento ou foto de visitante.
begin;
create table public.sdv_gestao_metricas (
 implantacao_id uuid not null, dia date not null,
 dados jsonb not null check(jsonb_typeof(dados)='object'),
 updated_at timestamptz not null default now(),
 primary key(implantacao_id,dia)
);
-- Quem pode entrar: espelho dos usuários com Dashboard no sistema local.
-- A senha fica no Supabase Auth; o servidor local não guarda.
create table public.sdv_gestao_gestores (
 implantacao_id uuid not null, email text not null check(email = lower(email) and length(email) between 5 and 254),
 nome text, user_id uuid, ativo boolean not null default true,
 updated_at timestamptz not null default now(),
 primary key(implantacao_id,email)
);
alter table public.sdv_gestao_metricas enable row level security;
alter table public.sdv_gestao_gestores enable row level security;
revoke all on public.sdv_gestao_metricas, public.sdv_gestao_gestores from public, anon, authenticated;
create policy sdv_gestao_metricas_sem_acesso_direto on public.sdv_gestao_metricas for all to anon, authenticated using (false) with check (false);
create policy sdv_gestao_gestores_sem_acesso_direto on public.sdv_gestao_gestores for all to anon, authenticated using (false) with check (false);
commit;
