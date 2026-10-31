-- Run in Supabase Dashboard > SQL Editor

-- 1) Profiles (one per auth user). Role can ONLY be changed by an admin via SQL/service role.
create table if not exists public.profiles (
  id             uuid primary key references auth.users(id) on delete cascade,
  role           text not null default 'student' check (role in ('student','admin')),
  first_name     text not null default '',
  last_name      text not null default '',
  student_number text unique,
  is_active      boolean not null default true,
  created_at     timestamptz not null default now()
);
alter table public.profiles enable row level security;

-- Users may read ONLY their own profile. No insert/update/delete policies = no client writes.
drop policy if exists "read own profile" on public.profiles;
create policy "read own profile" on public.profiles
  for select to authenticated using (id = auth.uid());

-- 2) Auto-create the profile on signup. Role is ALWAYS 'student' here (never trusted from client metadata).
create or replace function public.handle_new_user()
returns trigger language plpgsql security definer set search_path = public as $$
begin
  -- DB-level guard so the domain rule can't be bypassed by calling the Auth API directly.
  -- NOTE: admin accounts created in the dashboard also pass through this; use a PLM email or adjust.
  if lower(new.email) not like '%@plm.edu.ph' then
    raise exception 'Only PLM email addresses are allowed';
  end if;

  insert into public.profiles (id, role, first_name, last_name, student_number)
  values (
    new.id, 'student',
    left(trim(coalesce(new.raw_user_meta_data->>'first_name','')), 100),
    left(trim(coalesce(new.raw_user_meta_data->>'last_name','')), 100),
    nullif(left(trim(coalesce(new.raw_user_meta_data->>'student_number','')), 20), '')
  );
  return new;
end $$;

drop trigger if exists on_auth_user_created on auth.users;
create trigger on_auth_user_created after insert on auth.users
  for each row execute function public.handle_new_user();

-- 3) Login audit trail (who logged in, when, from where) + brute-force throttling.
--    RLS enabled with NO policies => only the server's service-role key can touch it.
create table if not exists public.login_audit (
  id         bigint generated always as identity primary key,
  user_id    uuid,
  email      text not null,
  ip         text,
  portal     text,
  success    boolean not null,
  reason     text not null,   -- ok | bad_credentials | unverified | not_authorized | locked
  user_agent text,
  created_at timestamptz not null default now()
);
alter table public.login_audit enable row level security;
create index if not exists login_audit_email_idx on public.login_audit (email, created_at desc);
create index if not exists login_audit_ip_idx    on public.login_audit (ip, created_at desc);

-- 4) Make someone an admin (1 admin = 1 account). Create the user first in
--    Authentication > Users > Add user (tick "Auto Confirm User"), then:
-- update public.profiles set role = 'admin', first_name = 'Juan', last_name = 'Dela Cruz'
--   where id = (select id from auth.users where email = 'admin1@plm.edu.ph');
