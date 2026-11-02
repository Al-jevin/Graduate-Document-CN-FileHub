-- =====================================================================
-- CN FILEHUB: database schema, security policies, functions, storage
-- Run once in Supabase Dashboard > SQL Editor > New query > Run.
-- All writes go through PHP using the service-role key. Signed-in
-- admins get read-only access through RLS as a second line of defense.
-- =====================================================================

create extension if not exists citext;
create extension if not exists pgcrypto;

-- ---------- Tables ----------------------------------------------------

create table if not exists public.profiles (
  id               uuid primary key references auth.users(id) on delete cascade,
  username         citext not null unique
                     check (username ~ '^[A-Za-z0-9_.]{4,30}$'),
  first_name       text not null check (length(btrim(first_name)) > 0),
  last_name        text not null check (length(btrim(last_name)) > 0),
  employee_number  text not null unique check (length(btrim(employee_number)) between 3 and 30),
  position         text not null check (length(btrim(position)) > 0),
  email            citext not null unique,
  role             text not null default 'admin' check (role in ('admin')),
  is_active        boolean not null default true,
  created_at       timestamptz not null default now()
);

create table if not exists public.students (
  id                   uuid primary key default gen_random_uuid(),
  student_number       text not null unique check (student_number ~ '^[0-9]{4}-[0-9]{5}$'),
  first_name           text not null check (length(btrim(first_name)) > 0),
  last_name            text not null check (length(btrim(last_name)) > 0),
  email                citext not null,
  graduate_year_level  text not null check (length(btrim(graduate_year_level)) > 0),
  section_cohort       text not null check (length(btrim(section_cohort)) > 0),
  is_former            boolean not null default false,
  academic_year        text,
  created_by           uuid references public.profiles(id),
  created_at           timestamptz not null default now(),
  updated_at           timestamptz not null default now(),
  deleted_at           timestamptz,
  -- Former student => academic_year is NULL. Otherwise it must look like 2025-2026.
  constraint students_academic_year_rule check (
    (is_former and academic_year is null)
    or (not is_former and academic_year ~ '^[0-9]{4}-[0-9]{4}$')
  )
);

create table if not exists public.document_types (
  id          smallint primary key generated always as identity,
  name        text not null unique,
  sort_order  smallint not null,
  is_active   boolean not null default true
);

create table if not exists public.tracking_counters (
  year        integer primary key,
  last_value  integer not null default 0
);

create table if not exists public.submissions (
  id                uuid primary key default gen_random_uuid(),
  tracking_id       text not null unique,
  student_id        uuid not null references public.students(id),
  document_type_id  smallint not null references public.document_types(id),
  status            text not null default 'Submitted'
                      check (status in ('Submitted','Accepted','Rejected','Completed')),
  remarks           text,
  reviewed_by       uuid references public.profiles(id),
  reviewed_at       timestamptz,
  created_by        uuid not null references public.profiles(id),
  created_at        timestamptz not null default now(),
  deleted_at        timestamptz,
  deleted_by        uuid references public.profiles(id)
);

create table if not exists public.submission_files (
  id                 uuid primary key default gen_random_uuid(),
  submission_id      uuid not null unique references public.submissions(id),
  storage_path       text not null unique,
  original_filename  text not null,
  file_size          integer not null check (file_size > 0 and file_size <= 10485760),
  sha256             text not null check (sha256 ~ '^[0-9a-f]{64}$'),
  uploaded_at        timestamptz not null default now()
);

create table if not exists public.submission_status_history (
  id             bigint primary key generated always as identity,
  submission_id  uuid not null references public.submissions(id),
  old_status     text not null,
  new_status     text not null,
  remarks        text,
  changed_by     uuid not null references public.profiles(id),
  changed_at     timestamptz not null default now()
);

create table if not exists public.activity_logs (
  id           bigint primary key generated always as identity,
  actor_id     uuid references public.profiles(id),
  action       text not null,
  entity_type  text,
  entity_id    text,
  details      jsonb not null default '{}'::jsonb,
  ip_address   text,
  created_at   timestamptz not null default now()
);

create table if not exists public.auth_attempts (
  id          bigint primary key generated always as identity,
  identifier  text not null,
  kind        text not null check (kind in ('login','register','reset')),
  ip_address  text,
  succeeded   boolean not null default false,
  created_at  timestamptz not null default now()
);

-- ---------- Indexes ---------------------------------------------------

create index if not exists idx_submissions_status_created on public.submissions (status, created_at desc) where deleted_at is null;
create index if not exists idx_submissions_created        on public.submissions (created_at desc) where deleted_at is null;
create index if not exists idx_submissions_reviewed       on public.submissions (reviewed_at desc) where deleted_at is null and reviewed_at is not null;
create index if not exists idx_submissions_student        on public.submissions (student_id);
create index if not exists idx_students_name              on public.students (lower(last_name), lower(first_name)) where deleted_at is null;
create index if not exists idx_history_submission         on public.submission_status_history (submission_id, changed_at desc);
create index if not exists idx_activity_created           on public.activity_logs (created_at desc);
create index if not exists idx_attempts_lookup            on public.auth_attempts (identifier, kind, created_at desc);

-- ---------- Seed: the seven approved document types -------------------

insert into public.document_types (name, sort_order) values
  ('Birth Certificate', 1),
  ('Transcript of Records', 2),
  ('Curriculum Vitae', 3),
  ('Recommendation Letter', 4),
  ('PRC PNLE Certification of Board Rating', 5),
  ('Summary of Related Learning Experience', 6),
  ('Other Submission', 7)
on conflict (name) do nothing;

-- ---------- Helper functions & triggers -------------------------------

create or replace function public.set_updated_at() returns trigger
language plpgsql as $$
begin new.updated_at = now(); return new; end $$;

drop trigger if exists trg_students_updated on public.students;
create trigger trg_students_updated before update on public.students
  for each row execute function public.set_updated_at();

create or replace function public.is_active_admin() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (
    select 1 from public.profiles p
    where p.id = auth.uid() and p.is_active and p.role = 'admin'
  );
$$;

-- Username -> email lookup, callable only by the PHP server (service role).
create or replace function public.resolve_login_email(p_username text) returns text
language sql stable security definer set search_path = public as $$
  select email::text from public.profiles
  where username = p_username::citext and is_active limit 1;
$$;

-- Creates a submission, its file record, and its tracking ID in one transaction.
-- Tracking ID: CN-{Document Type}-{YYYY}-{NNNN}; one running count per year.
create or replace function public.create_submission(
  p_id uuid, p_student_id uuid, p_document_type_id smallint, p_created_by uuid,
  p_storage_path text, p_original_filename text, p_file_size integer, p_sha256 text
) returns public.submissions
language plpgsql security definer set search_path = public as $$
declare
  v_year integer := extract(year from (now() at time zone 'Asia/Manila'))::integer;
  v_seq  integer;
  v_type text;
  v_row  public.submissions;
begin
  select name into v_type from public.document_types where id = p_document_type_id and is_active;
  if v_type is null then raise exception 'Unknown document type' using errcode = '22023'; end if;

  if not exists (select 1 from public.students where id = p_student_id and deleted_at is null) then
    raise exception 'Student not found' using errcode = 'P0002';
  end if;

  insert into public.tracking_counters (year, last_value) values (v_year, 1)
  on conflict (year) do update set last_value = public.tracking_counters.last_value + 1
  returning last_value into v_seq;

  insert into public.submissions (id, tracking_id, student_id, document_type_id, created_by)
  values (p_id, format('CN-%s-%s-%s', v_type, v_year, lpad(v_seq::text, 4, '0')),
          p_student_id, p_document_type_id, p_created_by)
  returning * into v_row;

  insert into public.submission_files (submission_id, storage_path, original_filename, file_size, sha256)
  values (v_row.id, p_storage_path, p_original_filename, p_file_size, p_sha256);

  insert into public.activity_logs (actor_id, action, entity_type, entity_id, details)
  values (p_created_by, 'submission.create', 'submission', v_row.id::text,
          jsonb_build_object('tracking_id', v_row.tracking_id));

  return v_row;
end $$;

-- Sets a review result. Works on first review and on re-review; every change is kept in history.
create or replace function public.review_submission(
  p_submission_id uuid, p_status text, p_remarks text, p_admin_id uuid
) returns public.submissions
language plpgsql security definer set search_path = public as $$
declare
  v_old public.submissions;
  v_new public.submissions;
begin
  if p_status not in ('Accepted','Rejected','Completed') then
    raise exception 'Invalid status' using errcode = '22023';
  end if;

  select * into v_old from public.submissions
  where id = p_submission_id and deleted_at is null for update;
  if not found then raise exception 'Submission not found' using errcode = 'P0002'; end if;

  update public.submissions
     set status = p_status, remarks = nullif(btrim(p_remarks), ''),
         reviewed_by = p_admin_id, reviewed_at = now()
   where id = p_submission_id
   returning * into v_new;

  insert into public.submission_status_history (submission_id, old_status, new_status, remarks, changed_by)
  values (v_new.id, v_old.status, v_new.status, v_new.remarks, p_admin_id);

  insert into public.activity_logs (actor_id, action, entity_type, entity_id, details)
  values (p_admin_id, 'submission.review', 'submission', v_new.id::text,
          jsonb_build_object('tracking_id', v_new.tracking_id, 'from', v_old.status, 'to', v_new.status));

  return v_new;
end $$;

-- Soft delete: hides the row everywhere; the PDF stays in storage.
create or replace function public.soft_delete_submission(p_submission_id uuid, p_admin_id uuid)
returns void
language plpgsql security definer set search_path = public as $$
declare v_tracking text;
begin
  update public.submissions
     set deleted_at = now(), deleted_by = p_admin_id
   where id = p_submission_id and deleted_at is null
   returning tracking_id into v_tracking;
  if v_tracking is null then raise exception 'Submission not found' using errcode = 'P0002'; end if;

  insert into public.activity_logs (actor_id, action, entity_type, entity_id, details)
  values (p_admin_id, 'submission.delete', 'submission', p_submission_id::text,
          jsonb_build_object('tracking_id', v_tracking));
end $$;

-- Only the PHP server (service role) may call these.
revoke all on function public.resolve_login_email(text) from public, anon, authenticated;
revoke all on function public.create_submission(uuid,uuid,smallint,uuid,text,text,integer,text) from public, anon, authenticated;
revoke all on function public.review_submission(uuid,text,text,uuid) from public, anon, authenticated;
revoke all on function public.soft_delete_submission(uuid,uuid) from public, anon, authenticated;
grant execute on function public.resolve_login_email(text) to service_role;
grant execute on function public.create_submission(uuid,uuid,smallint,uuid,text,text,integer,text) to service_role;
grant execute on function public.review_submission(uuid,text,text,uuid) to service_role;
grant execute on function public.soft_delete_submission(uuid,uuid) to service_role;

-- ---------- Row Level Security ----------------------------------------

alter table public.profiles                  enable row level security;
alter table public.students                  enable row level security;
alter table public.document_types            enable row level security;
alter table public.tracking_counters         enable row level security;
alter table public.submissions               enable row level security;
alter table public.submission_files          enable row level security;
alter table public.submission_status_history enable row level security;
alter table public.activity_logs             enable row level security;
alter table public.auth_attempts             enable row level security;

-- Visitors who are not signed in get nothing. Signed-in admins get read-only access.
revoke all on all tables in schema public from anon, authenticated;
alter default privileges in schema public revoke all on tables from anon, authenticated;
grant select on public.profiles, public.students, public.document_types, public.submissions,
                public.submission_files, public.submission_status_history, public.activity_logs
  to authenticated;

drop policy if exists admin_read on public.profiles;
create policy admin_read on public.profiles for select to authenticated using (public.is_active_admin());
drop policy if exists admin_read on public.students;
create policy admin_read on public.students for select to authenticated
  using (public.is_active_admin() and deleted_at is null);
drop policy if exists admin_read on public.document_types;
create policy admin_read on public.document_types for select to authenticated using (public.is_active_admin());
drop policy if exists admin_read on public.submissions;
create policy admin_read on public.submissions for select to authenticated
  using (public.is_active_admin() and deleted_at is null);
drop policy if exists admin_read on public.submission_files;
create policy admin_read on public.submission_files for select to authenticated
  using (public.is_active_admin() and exists (
    select 1 from public.submissions s where s.id = submission_id and s.deleted_at is null));
drop policy if exists admin_read on public.submission_status_history;
create policy admin_read on public.submission_status_history for select to authenticated using (public.is_active_admin());
drop policy if exists admin_read on public.activity_logs;
create policy admin_read on public.activity_logs for select to authenticated using (public.is_active_admin());
-- tracking_counters and auth_attempts: RLS on, no policies => service role only.

-- ---------- Private storage bucket ------------------------------------

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('student-documents', 'student-documents', false, 10485760, array['application/pdf'])
on conflict (id) do update
  set public = false, file_size_limit = 10485760, allowed_mime_types = array['application/pdf'];
-- No storage policies on purpose: only the PHP server (service role) can read or write files.
-- Admins open PDFs through short-lived signed URLs that PHP issues after checking the session.
