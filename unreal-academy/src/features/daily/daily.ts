import type { Mission } from "@/features/missions/types";

/**
 * Daily Challenge — täglich rotierende Bau-/Debug-Mission mit XP-Bonus.
 * Reine, deterministische Auswahl (gleicher Tag → gleiche Mission), damit sie
 * auf jedem Gerät identisch ist und ohne Backend funktioniert.
 */

/** XP-Bonus der Daily Challenge (zusätzlich, +50 %). */
export const DAILY_BONUS = 0.5;

/** Stabiler Hash eines Strings (für die deterministische Tagesauswahl). */
function hashStr(s: string): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) {
    h = (h * 31 + s.charCodeAt(i)) | 0;
  }
  return Math.abs(h);
}

/** Pool der für die Daily geeigneten Missionen (nur Bau-/Debug-Aufgaben). */
export function dailyPool(missions: Mission[]): Mission[] {
  return missions.filter((m) => {
    const s = m.steps[0];
    return s.kind === "build" || s.kind === "debug";
  });
}

/** Wählt deterministisch die Mission des Tages (YYYY-MM-DD). */
export function selectDailyMission(
  day: string,
  missions: Mission[],
): Mission | undefined {
  const pool = dailyPool(missions);
  if (pool.length === 0) return undefined;
  return pool[hashStr(day) % pool.length];
}
