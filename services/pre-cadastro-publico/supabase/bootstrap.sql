-- Aplicar somente ao projeto isolado de homologação do portal. Sem dados pessoais.
begin;
create table public.sdv_portal_solicitacoes (
 id uuid primary key, implantacao_id uuid not null, upload_hash text not null,
 receipt_hash text not null, document_type text not null check(document_type in ('cnh','rg_cin')),
 protocol text unique, state text not null default 'rascunho' check(state in ('rascunho','enviado','importado')),
 dados jsonb, files jsonb, data_hash text, privacy_version text,
 review_status text not null default 'aguardando' check(review_status in ('aguardando','aprovado','rejeitado','correcao')),
 local_version integer not null default 0, created_at timestamptz not null default now(),
 submitted_at timestamptz, acknowledged_at timestamptz
);
create index sdv_portal_pending on public.sdv_portal_solicitacoes(implantacao_id,submitted_at,id) where state='enviado';
alter table public.sdv_portal_solicitacoes enable row level security;
create policy sdv_portal_sem_acesso_direto on public.sdv_portal_solicitacoes for all to anon,authenticated using(false) with check(false);
revoke all on public.sdv_portal_solicitacoes from public,anon,authenticated;
grant select,insert,update,delete on public.sdv_portal_solicitacoes to service_role;
create table public.sdv_portal_rate_limits (key text primary key, hits integer not null default 1, created_at timestamptz not null default now());
alter table public.sdv_portal_rate_limits enable row level security;
create policy sdv_portal_rate_sem_acesso_direto on public.sdv_portal_rate_limits for all to anon,authenticated using(false) with check(false);
revoke all on public.sdv_portal_rate_limits from public,anon,authenticated;
grant select,insert,update,delete on public.sdv_portal_rate_limits to service_role;
create function public.sdv_portal_rate(p_key text) returns integer language sql security invoker set search_path='' as $$
 insert into public.sdv_portal_rate_limits(key) values(p_key)
 on conflict(key) do update set hits=sdv_portal_rate_limits.hits+1 returning hits;
$$;
create function public.sdv_portal_ack(p_id uuid,p_tenant uuid,p_version integer,p_status text)
 returns boolean language plpgsql security invoker set search_path='' as $$
 begin
 if p_version < 1 or p_status not in ('aguardando','aprovado','rejeitado','correcao') then return false; end if;
 update public.sdv_portal_solicitacoes set state='importado',local_version=p_version,review_status=p_status,acknowledged_at=now()
 where id=p_id and implantacao_id=p_tenant and state in ('enviado','importado')
 and (local_version<p_version or (local_version=p_version and review_status=p_status));
 return found;
 end; $$;
revoke all on function public.sdv_portal_rate(text) from public,anon,authenticated;
revoke all on function public.sdv_portal_ack(uuid,uuid,integer,text) from public,anon,authenticated;
grant execute on function public.sdv_portal_rate(text) to service_role;
grant execute on function public.sdv_portal_ack(uuid,uuid,integer,text) to service_role;
insert into storage.buckets(id,name,public,file_size_limit,allowed_mime_types)
 values('pre-cadastro-privado','pre-cadastro-privado',false,8388608,array['image/jpeg','image/png','image/webp']);
commit;
-- Estrutura posterior: aplicar em seguida, na ordem, os arquivos de supabase/migrations/.
-- Não criar políticas públicas no bucket. Upload/download só por backend e URLs assinadas.
-- Retenção: remover rascunhos/objetos órfãos e contadores antigos conforme manual, antes da produção.
