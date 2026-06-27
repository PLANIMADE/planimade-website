import type { SupabaseClient } from "@supabase/supabase-js";
import {
  normalizeProgress,
  type LocalProgress,
} from "@/features/progression/local";

/**
 * Cloud-Persistenz des Fortschritts. Ein Zeilen-pro-Nutzer-Modell:
 * Tabelle `profiles` mit Spalte `progress` (jsonb). Schema & RLS: supabase/schema.sql.
 */

const TABLE = "profiles";

/** Lädt den Cloud-Stand eines Nutzers (oder null, wenn keiner existiert). */
export async function loadRemote(
  supabase: SupabaseClient,
  userId: string,
): Promise<LocalProgress | null> {
  const { data, error } = await supabase
    .from(TABLE)
    .select("progress")
    .eq("id", userId)
    .maybeSingle();
  if (error || !data?.progress) return null;
  return normalizeProgress(data.progress as Partial<LocalProgress>);
}

/** Schreibt den Stand eines Nutzers in die Cloud (Upsert). */
export async function saveRemote(
  supabase: SupabaseClient,
  userId: string,
  progress: LocalProgress,
): Promise<void> {
  await supabase.from(TABLE).upsert({
    id: userId,
    progress,
    updated_at: new Date().toISOString(),
  });
}
