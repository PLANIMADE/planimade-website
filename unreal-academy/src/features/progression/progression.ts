import type { Mission, Track } from "@/features/missions/types";

/**
 * Reine Progressions-Logik (UI-frei, testbar): Ränge, Streak-Übergänge, Badges.
 * Keine Persistenz, kein React — der Store (store.ts) setzt darauf auf.
 */

// ── Ränge ────────────────────────────────────────────────────────────────────

export interface Rank {
  minXp: number;
  title: string;
}

/** XP-Schwellen aus dem Phase-2-MVP-Tuning. */
export const RANKS: Rank[] = [
  { minXp: 0, title: "Blueprint Anfänger" },
  { minXp: 150, title: "Logic Builder" },
  { minXp: 400, title: "Gameplay Designer" },
  { minXp: 800, title: "System Architect" },
  { minXp: 1500, title: "Unreal Wizard" },
];

export interface RankInfo {
  index: number;
  rank: Rank;
  /** Nächster Rang oder null (Maximalrang erreicht). */
  next: Rank | null;
  /** Noch fehlende XP bis zum nächsten Rang (0 bei Maximalrang). */
  toNext: number;
  /** Fortschritt innerhalb des aktuellen Rang-Bands (0..1). */
  progress: number;
}

export function computeRank(xp: number): RankInfo {
  let index = 0;
  for (let i = 0; i < RANKS.length; i++) {
    if (xp >= RANKS[i].minXp) index = i;
  }
  const rank = RANKS[index];
  const next = RANKS[index + 1] ?? null;
  if (!next) {
    return { index, rank, next: null, toNext: 0, progress: 1 };
  }
  const band = next.minXp - rank.minXp;
  const into = xp - rank.minXp;
  return {
    index,
    rank,
    next,
    toNext: Math.max(0, next.minXp - xp),
    progress: band > 0 ? Math.min(1, into / band) : 0,
  };
}

// ── Datum & Streak ────────────────────────────────────────────────────────────

/** Lokales Datum als YYYY-MM-DD (streak-relevant ist der Kalendertag). */
export function dayStr(d: Date = new Date()): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, "0");
  const day = String(d.getDate()).padStart(2, "0");
  return `${y}-${m}-${day}`;
}

export interface Streak {
  count: number;
  /** Letzter aktiver Tag (YYYY-MM-DD) oder null. */
  lastDay: string | null;
}

/** Anzahl Kalendertage zwischen zwei YYYY-MM-DD-Strings (b - a). */
export function dayDiff(a: string, b: string): number {
  const da = new Date(a + "T00:00:00");
  const db = new Date(b + "T00:00:00");
  return Math.round((db.getTime() - da.getTime()) / 86_400_000);
}

/**
 * Streak nach Aktivität am Tag `today` fortschreiben:
 * - selber Tag → unverändert (mind. 1),
 * - gestern → +1,
 * - Lücke / erster Tag → zurück auf 1.
 */
export function advanceStreak(prev: Streak, today: string = dayStr()): Streak {
  if (!prev.lastDay) return { count: 1, lastDay: today };
  const diff = dayDiff(prev.lastDay, today);
  if (diff === 0) return { count: Math.max(1, prev.count), lastDay: today };
  if (diff === 1) return { count: prev.count + 1, lastDay: today };
  return { count: 1, lastDay: today };
}

/** Ist die Streak (relativ zu `today`) noch aktiv, oder abgelaufen? */
export function isStreakAlive(streak: Streak, today: string = dayStr()): boolean {
  if (!streak.lastDay) return false;
  return dayDiff(streak.lastDay, today) <= 1;
}

// ── Badges ─────────────────────────────────────────────────────────────────

export interface BadgeContext {
  xp: number;
  /** Mission-ID → Sterne. */
  completed: Record<string, number>;
  firstTry: string[];
  streak: Streak;
  missionsById: Record<string, Mission>;
  tracks: Track[];
}

export interface BadgeDef {
  id: string;
  title: string;
  icon: string;
  desc: string;
  earned: (ctx: BadgeContext) => boolean;
}

function completedMissions(ctx: BadgeContext): Mission[] {
  return Object.keys(ctx.completed)
    .map((id) => ctx.missionsById[id])
    .filter((m): m is Mission => !!m);
}

export const BADGES: BadgeDef[] = [
  {
    id: "first-blueprint",
    title: "Erster Blueprint",
    icon: "🧩",
    desc: "Schließe deine erste Bau-Mission ab.",
    earned: (ctx) =>
      completedMissions(ctx).some((m) => m.type === "build" || m.type === "boss"),
  },
  {
    id: "bug-hunter",
    title: "Bug-Jäger",
    icon: "🐛",
    desc: "Repariere deine erste Debug-Mission.",
    earned: (ctx) => completedMissions(ctx).some((m) => m.type === "debug"),
  },
  {
    id: "streak-3",
    title: "3-Tage-Streak",
    icon: "🔥",
    desc: "Sei drei Tage in Folge aktiv.",
    earned: (ctx) => ctx.streak.count >= 3,
  },
  {
    id: "first-try-pro",
    title: "Erst-Versuch-Profi",
    icon: "⚡",
    desc: "Löse 5 Missionen im ersten Versuch.",
    earned: (ctx) => ctx.firstTry.length >= 5,
  },
  {
    id: "path-master",
    title: "Pfad-Meister",
    icon: "👑",
    desc: "Schließe einen ganzen Lernpfad ab.",
    earned: (ctx) =>
      ctx.tracks.some(
        (t) => t.missions.length > 0 && t.missions.every((m) => ctx.completed[m.id]),
      ),
  },
];

/** IDs aller aktuell verdienten Badges. */
export function evaluateBadges(ctx: BadgeContext): string[] {
  return BADGES.filter((b) => b.earned(ctx)).map((b) => b.id);
}

export function getBadge(id: string): BadgeDef | undefined {
  return BADGES.find((b) => b.id === id);
}
