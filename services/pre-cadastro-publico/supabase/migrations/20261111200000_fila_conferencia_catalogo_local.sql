-- Aplicar somente no projeto isolado do portal (sdv-santa-rita), ANTES de publicar o código
-- que depende desta estrutura. Não aplicar no projeto certificados. Sem dados pessoais.
begin;
-- 1) Falhas de importação: retentativa com espera e sinalização para conferência.
alter table public.sdv_portal_solicitacoes drop constraint if exists sdv_portal_solicitacoes_state_check;
alter table public.sdv_portal_solicitacoes add constraint sdv_portal_solicitacoes_state_check
 check(state in ('rascunho','enviado','importado','conferencia'));
alter table public.sdv_portal_solicitacoes
 add column if not exists import_attempts integer not null default 0,
 add column if not exists next_attempt_at timestamptz,
 add column if not exists last_failure text check(last_failure is null or last_failure ~ '^[a-z_]{3,40}$');
drop index if exists public.sdv_portal_pending;
create index sdv_portal_pending on public.sdv_portal_solicitacoes(implantacao_id,submitted_at,id) where state='enviado';

create function public.sdv_portal_falha(p_id uuid,p_tenant uuid,p_code text,p_max integer)
 returns table(state text,import_attempts integer) language plpgsql security invoker set search_path='' as $$
 begin
 if p_code !~ '^[a-z_]{3,40}$' or p_max < 1 then return; end if;
 return query
 update public.sdv_portal_solicitacoes s set
  import_attempts=s.import_attempts+1, last_failure=p_code,
  next_attempt_at=now()+make_interval(mins=>least(60,power(2,s.import_attempts)::integer)),
  state=case when s.import_attempts+1>=p_max then 'conferencia' else s.state end
 where s.id=p_id and s.implantacao_id=p_tenant and s.state='enviado'
 returning s.state,s.import_attempts;
 end; $$;

create function public.sdv_portal_reabrir(p_id uuid,p_tenant uuid)
 returns boolean language plpgsql security invoker set search_path='' as $$
 begin
 update public.sdv_portal_solicitacoes set state='enviado',import_attempts=0,next_attempt_at=null
 where id=p_id and implantacao_id=p_tenant and state='conferencia';
 return found;
 end; $$;

-- 2) Catálogo de imóveis publicado pelo sistema local (uma lista por implantação).
create table public.sdv_portal_catalogos (
 implantacao_id uuid primary key, itens jsonb not null default '[]'::jsonb check(jsonb_typeof(itens)='array'),
 hash text not null, updated_at timestamptz not null default now()
);
alter table public.sdv_portal_catalogos enable row level security;
create policy sdv_portal_catalogo_sem_acesso_direto on public.sdv_portal_catalogos for all to anon,authenticated using(false) with check(false);
revoke all on public.sdv_portal_catalogos from public,anon,authenticated;
grant select,insert,update,delete on public.sdv_portal_catalogos to service_role;

revoke all on function public.sdv_portal_falha(uuid,uuid,text,integer) from public,anon,authenticated;
revoke all on function public.sdv_portal_reabrir(uuid,uuid) from public,anon,authenticated;
grant execute on function public.sdv_portal_falha(uuid,uuid,text,integer) to service_role;
grant execute on function public.sdv_portal_reabrir(uuid,uuid) to service_role;
commit;
