import type { LocalProgress } from "./local";
import type { Streak } from "./progression";

/**
 * Konfliktfreies Zusammenführen zweier Fortschritts-Stände (z.B. lokaler
 * Gast-Fortschritt + Cloud beim ersten Login, oder zwei Geräte).
 *
 * Grundsatz: „immer der weiter fortgeschrittene Wert gewinnt" — verlustfrei und
 * deterministisch (kommutativ), damit kein Fortschritt verschwindet und das
 * Ergebnis nicht von der Reihenfolge abhängt.
 */

/** Vereinigung ohne Duplikate, stabil sortiert (reihenfolgeunabhängig). */
function union(a: string[], b: string[]): string[] {
  return [...new Set([...a, ...b])].sort();
}

/** Pro Mission die höhere Sternezahl behalten. */
function mergeStars(
  a: Record<string, number>,
  b: Record<string, number>,
): Record<string, number> {
  const out: Record<string, number> = { ...a };
  for (const [id, stars] of Object.entries(b)) {
    out[id] = Math.max(out[id] ?? 0, stars);
  }
  return out;
}

/** Streak mit der höheren Zahl; bei Gleichstand der spätere aktive Tag. */
function pickStreak(a: Streak, b: Streak): Streak {
  if (b.count > a.count) return b;
  if (a.count > b.count) return a;
  return (b.lastDay ?? "") > (a.lastDay ?? "") ? b : a;
}

/** Späteres Datum (YYYY-MM-DD sortiert lexikografisch korrekt). */
function laterDay(a: string | null, b: string | null): string | null {
  if (!a) return b;
  if (!b) return a;
  return a >= b ? a : b;
}

/** Ziel: nicht-null bevorzugen; bei Konflikt deterministisch (kleineres). */
function pickGoal(a: string | null, b: string | null): string | null {
  if (!a) return b;
  if (!b) return a;
  return a <= b ? a : b;
}

export function mergeProgress(
  a: LocalProgress,
  b: LocalProgress,
): LocalProgress {
  return {
    xp: Math.max(a.xp, b.xp),
    completed: mergeStars(a.completed, b.completed),
    firstTry: union(a.firstTry, b.firstTry),
    streak: pickStreak(a.streak, b.streak),
    badges: union(a.badges, b.badges),
    dailyDoneDay: laterDay(a.dailyDoneDay, b.dailyDoneDay),
    onboarded: a.onboarded || b.onboarded,
    goal: pickGoal(a.goal, b.goal),
  };
}
