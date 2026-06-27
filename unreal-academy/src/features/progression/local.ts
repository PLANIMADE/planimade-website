"use client";

import { useSyncExternalStore } from "react";
import { allMissions, tracks } from "@/data/missions";
import type { Mission } from "@/features/missions/types";
import {
  advanceStreak,
  computeRank,
  evaluateBadges,
  getBadge,
  type BadgeDef,
  type RankInfo,
  type Streak,
} from "./progression";

/**
 * Reaktiver, lokaler Fortschritts-Speicher (Gast-Modus / Offline-Fallback).
 *
 * Quelle der Wahrheit ist localStorage; Komponenten abonnieren Änderungen über
 * `useProgress()` (useSyncExternalStore), sodass XP/Streak/Badges ohne Reload
 * aktualisieren. Der spätere Supabase-Sync setzt auf diesem Sockel auf.
 */

const KEY = "ua.progress.v1";

export interface LocalProgress {
  xp: number;
  /** Mission-ID → erreichte Sternezahl (1–3). */
  completed: Record<string, number>;
  /** Mission-IDs, im ersten Versuch (ohne Fehlversuch) gelöst. */
  firstTry: string[];
  streak: Streak;
  /** IDs verdienter Badges. */
  badges: string[];
}

const EMPTY: LocalProgress = {
  xp: 0,
  completed: {},
  firstTry: [],
  streak: { count: 0, lastDay: null },
  badges: [],
};

function isBrowser(): boolean {
  return typeof window !== "undefined" && !!window.localStorage;
}

function readRaw(): LocalProgress {
  if (!isBrowser()) return EMPTY;
  try {
    const raw = window.localStorage.getItem(KEY);
    if (!raw) return EMPTY;
    const p = JSON.parse(raw) as Partial<LocalProgress>;
    return {
      xp: p.xp ?? 0,
      completed: p.completed ?? {},
      firstTry: p.firstTry ?? [],
      streak: p.streak ?? { count: 0, lastDay: null },
      badges: p.badges ?? [],
    };
  } catch {
    return EMPTY;
  }
}

// ── Reaktiver Store ──────────────────────────────────────────────────────────

let cache: LocalProgress | null = null;
const listeners = new Set<() => void>();

function getSnapshot(): LocalProgress {
  if (cache === null) cache = readRaw();
  return cache;
}

function getServerSnapshot(): LocalProgress {
  return EMPTY;
}

function emit(): void {
  cache = readRaw();
  listeners.forEach((l) => l());
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  // Änderungen aus anderen Tabs übernehmen.
  const onStorage = (e: StorageEvent) => {
    if (e.key === KEY) emit();
  };
  if (isBrowser()) window.addEventListener("storage", onStorage);
  return () => {
    listeners.delete(listener);
    if (isBrowser()) window.removeEventListener("storage", onStorage);
  };
}

function write(p: LocalProgress): void {
  if (isBrowser()) {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(p));
    } catch {
      /* Speicher voll / privat – im Gast-Modus tolerierbar. */
    }
  }
  cache = p;
  listeners.forEach((l) => l());
}

/** React-Hook: liefert den aktuellen Fortschritt reaktiv. */
export function useProgress(): LocalProgress {
  return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}

/** Einmaliges, nicht-reaktives Lesen (z.B. außerhalb von React). */
export function loadProgress(): LocalProgress {
  return readRaw();
}

// ── Mutationen ───────────────────────────────────────────────────────────────

const missionsById: Record<string, Mission> = Object.fromEntries(
  allMissions.map((m) => [m.id, m]),
);

export interface CompletionResult {
  progress: LocalProgress;
  /** Tatsächlich gutgeschriebene XP (0, falls keine Verbesserung). */
  awardedXp: number;
  /** Neu verdiente Badges in diesem Abschluss. */
  newBadges: BadgeDef[];
  rank: RankInfo;
  rankedUp: boolean;
}

/**
 * Verbucht einen Mission-Abschluss: XP (nur bei Sterne-Verbesserung),
 * Erst-Versuch-Marker, Streak (Aktivität heute) und Badges. Reaktiv.
 */
export function recordCompletion(
  missionId: string,
  stars: number,
  earnedXp: number,
  firstTry: boolean,
): CompletionResult {
  const prev = readRaw();
  const rankBefore = computeRank(prev.xp);

  const next: LocalProgress = {
    xp: prev.xp,
    completed: { ...prev.completed },
    firstTry: [...prev.firstTry],
    streak: advanceStreak(prev.streak),
    badges: [...prev.badges],
  };

  const prevStars = prev.completed[missionId] ?? 0;
  let awardedXp = 0;
  if (stars > prevStars) {
    awardedXp = earnedXp;
    next.xp += earnedXp;
    next.completed[missionId] = stars;
  }
  if (firstTry && !next.firstTry.includes(missionId)) {
    next.firstTry.push(missionId);
  }

  // Badges neu bewerten und neu verdiente ermitteln.
  const earnedIds = evaluateBadges({
    xp: next.xp,
    completed: next.completed,
    firstTry: next.firstTry,
    streak: next.streak,
    missionsById,
    tracks,
  });
  const newBadgeIds = earnedIds.filter((id) => !prev.badges.includes(id));
  next.badges = earnedIds;

  write(next);

  const rank = computeRank(next.xp);
  return {
    progress: next,
    awardedXp,
    newBadges: newBadgeIds
      .map((id) => getBadge(id))
      .filter((b): b is BadgeDef => !!b),
    rank,
    rankedUp: rank.index > rankBefore.index,
  };
}

export function isCompleted(missionId: string, p?: LocalProgress): boolean {
  return (p ?? readRaw()).completed[missionId] !== undefined;
}
