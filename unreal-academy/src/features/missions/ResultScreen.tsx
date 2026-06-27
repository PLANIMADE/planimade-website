"use client";

import Link from "next/link";
import type { BadgeDef } from "@/features/progression/progression";
import type { Mission } from "./types";

/** Abschluss-Screen einer Mission: Sterne, XP, Streak, Rang, Badges, Weiter. */
export function ResultScreen({
  mission,
  stars,
  earnedXp,
  dailyBonus = 0,
  totalXp,
  firstTry,
  streak,
  rankedUp,
  rankTitle,
  newBadges,
  nextMission,
  onRetry,
}: {
  mission: Mission;
  stars: number;
  earnedXp: number;
  dailyBonus?: number;
  totalXp: number;
  firstTry: boolean;
  streak: number;
  rankedUp: boolean;
  rankTitle: string;
  newBadges: BadgeDef[];
  nextMission: Mission | null;
  onRetry: () => void;
}) {
  return (
    <div className="mx-auto flex max-w-md flex-col items-center gap-6 px-6 py-12 text-center">
      <div className="animate-xp-pop text-sm font-semibold uppercase tracking-widest text-accent">
        Mission abgeschlossen
      </div>

      <h1 className="text-2xl font-extrabold text-text">{mission.title}</h1>

      {/* Sterne */}
      <div className="flex gap-2 text-4xl">
        {[1, 2, 3].map((s) => (
          <span
            key={s}
            className={s <= stars ? "text-xp" : "text-border"}
            aria-hidden
          >
            ★
          </span>
        ))}
      </div>

      {/* XP */}
      <div className="flex flex-col items-center gap-1">
        <span className="animate-xp-pop text-5xl font-black text-xp">
          +{earnedXp}
        </span>
        <span className="text-xs uppercase tracking-wider text-muted">
          XP verdient
        </span>
      </div>

      {firstTry && (
        <div className="rounded-full border border-success/40 bg-success/10 px-4 py-1.5 text-xs font-medium text-success">
          ⚡ Erst-Versuch-Bonus (+20 %)
        </div>
      )}

      {dailyBonus > 0 && (
        <div className="animate-xp-pop rounded-full border border-streak/40 bg-streak/10 px-4 py-1.5 text-xs font-medium text-streak">
          🔥 Daily Challenge +{dailyBonus} XP (+50 %)
        </div>
      )}

      {/* Rang-Aufstieg */}
      {rankedUp && (
        <div className="animate-xp-pop rounded-xl border border-accent/50 bg-accent/10 px-5 py-2.5 text-sm font-semibold text-accent">
          ⬆ Neuer Rang: {rankTitle}
        </div>
      )}

      {/* Neue Badges */}
      {newBadges.length > 0 && (
        <div className="flex flex-col items-center gap-2">
          <span className="text-xs uppercase tracking-wider text-muted">
            Neues Abzeichen
          </span>
          <div className="flex flex-wrap justify-center gap-2">
            {newBadges.map((b) => (
              <span
                key={b.id}
                className="animate-xp-pop inline-flex items-center gap-1.5 rounded-full border border-xp/40 bg-xp/10 px-3 py-1.5 text-xs font-medium text-text"
              >
                <span aria-hidden>{b.icon}</span> {b.title}
              </span>
            ))}
          </div>
        </div>
      )}

      <div className="flex items-center gap-4 text-sm text-muted">
        <span>
          Gesamt: <span className="font-semibold text-text">{totalXp} XP</span>
        </span>
        {streak > 0 && (
          <span className="inline-flex items-center gap-1">
            <span className="text-streak" aria-hidden>🔥</span>
            <span className="font-semibold text-text">{streak}</span>
            {streak === 1 ? "Tag" : "Tage"}
          </span>
        )}
      </div>

      <div className="mt-2 flex w-full flex-col gap-2.5">
        {nextMission ? (
          <Link
            href={`/mission/${nextMission.id}`}
            className="rounded-xl bg-accent px-6 py-3 font-semibold text-bg shadow-glow transition hover:brightness-110"
          >
            Weiter → {nextMission.title}
          </Link>
        ) : (
          <div className="rounded-xl border border-border bg-surface/50 px-6 py-3 text-sm text-muted">
            🎉 Pfad abgeschlossen — weitere Pfade folgen.
          </div>
        )}

        <button
          onClick={onRetry}
          className="rounded-xl border border-border bg-surface px-6 py-2.5 text-sm text-muted transition hover:text-text"
        >
          ↺ Nochmal spielen
        </button>
        <Link
          href="/learn"
          className="rounded-xl px-6 py-2.5 text-sm text-accent transition hover:underline"
        >
          Zur Lernkarte
        </Link>
      </div>
    </div>
  );
}
