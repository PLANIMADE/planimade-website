import { createClient, type SupabaseClient } from "@supabase/supabase-js";

/**
 * Supabase-Client — optional. Ist nur aktiv, wenn die beiden Env-Variablen
 * gesetzt sind. Ohne sie läuft die App vollständig im lokalen Gast-Modus
 * (kein Login, kein Cloud-Sync) — nichts bricht.
 *
 *   NEXT_PUBLIC_SUPABASE_URL=...
 *   NEXT_PUBLIC_SUPABASE_ANON_KEY=...
 */

const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
const anonKey = process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY;

let client: SupabaseClient | null = null;

export function isSupabaseConfigured(): boolean {
  return Boolean(url && anonKey);
}

/** Liefert den (lazy erzeugten) Client oder null, wenn nicht konfiguriert. */
export function getSupabase(): SupabaseClient | null {
  if (!isSupabaseConfigured()) return null;
  if (!client) {
    client = createClient(url!, anonKey!, {
      auth: { persistSession: true, autoRefreshToken: true },
    });
  }
  return client;
}
