-- Unreal Academy – Supabase-Schema (im SQL-Editor des Projekts ausführen).
--
-- Ein Zeilen-pro-Nutzer-Modell: der komplette Fortschritt liegt als JSON in
-- `profiles.progress`. Reicht fürs MVP und passt 1:1 zum lokalen Store
-- (LocalProgress). Row Level Security sorgt dafür, dass jede:r nur die eigene
-- Zeile lesen/schreiben kann.

create table if not exists public.profiles (
  id          uuid primary key references auth.users (id) on delete cascade,
  progress    jsonb not null default '{}'::jsonb,
  updated_at  timestamptz not null default now()
);

alter table public.profiles enable row level security;

-- Nur eigene Zeile lesen
drop policy if exists "profiles_select_own" on public.profiles;
create policy "profiles_select_own"
  on public.profiles for select
  using (auth.uid() = id);

-- Nur eigene Zeile anlegen
drop policy if exists "profiles_insert_own" on public.profiles;
create policy "profiles_insert_own"
  on public.profiles for insert
  with check (auth.uid() = id);

-- Nur eigene Zeile aktualisieren
drop policy if exists "profiles_update_own" on public.profiles;
create policy "profiles_update_own"
  on public.profiles for update
  using (auth.uid() = id)
  with check (auth.uid() = id);
