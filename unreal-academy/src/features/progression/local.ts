/**
 * Minimaler lokaler Fortschritts-Speicher (Gast-Modus / Offline-Fallback).
 *
 * Bewusst klein gehalten: speichert abgeschlossene Missionen, Gesamt-XP und
 * Erst-Versuch-Marker im localStorage. Die vollständige Progression
 * (Streak, Ränge, Badges, Supabase-Sync) folgt im Progression-Schritt — diese
 * Funktionen bilden den lokalen Sockel, auf dem sie aufsetzt.
 */

const KEY = "ua.progress.v1";

export interface LocalProgress {
  /** XP-Summe. */
  xp: number;
  /** Mission-ID → erreichte Sternezahl (1–3). */
  completed: Record<string, number>;
  /** Mission-IDs, die im ersten Versuch (ohne Fehlversuch) gelöst wurden. */
  firstTry: string[];
}

const EMPTY: LocalProgress = { xp: 0, completed: {}, firstTry: [] };

function isBrowser(): boolean {
  return typeof window !== "undefined" && !!window.localStorage;
}

export function loadProgress(): LocalProgress {
  if (!isBrowser()) return { ...EMPTY };
  try {
    const raw = window.localStorage.getItem(KEY);
    if (!raw) return { ...EMPTY };
    const parsed = JSON.parse(raw) as Partial<LocalProgress>;
    return {
      xp: parsed.xp ?? 0,
      completed: parsed.completed ?? {},
      firstTry: parsed.firstTry ?? [],
    };
  } catch {
    return { ...EMPTY };
  }
}

function save(p: LocalProgress): void {
  if (!isBrowser()) return;
  try {
    window.localStorage.setItem(KEY, JSON.stringify(p));
  } catch {
    /* Speicher voll / privat – im Gast-Modus tolerierbar. */
  }
}

/**
 * Verbucht einen Mission-Abschluss. XP werden nur beim Verbessern der
 * Sternezahl gutgeschrieben (kein XP-Farming durch Wiederholen).
 */
export function recordCompletion(
  missionId: string,
  stars: number,
  earnedXp: number,
  firstTry: boolean,
): LocalProgress {
  const p = loadProgress();
  const prevStars = p.completed[missionId] ?? 0;
  if (stars > prevStars) {
    p.xp += earnedXp;
    p.completed[missionId] = stars;
  }
  if (firstTry && !p.firstTry.includes(missionId)) {
    p.firstTry.push(missionId);
  }
  save(p);
  return p;
}

export function isCompleted(missionId: string, p?: LocalProgress): boolean {
  return (p ?? loadProgress()).completed[missionId] !== undefined;
}
