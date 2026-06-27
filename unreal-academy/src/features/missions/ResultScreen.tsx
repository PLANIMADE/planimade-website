"use client";

import Link from "next/link";
import type { Mission } from "./types";

/** Abschluss-Screen einer Mission: Sterne, XP, Erst-Versuch-Bonus, Weiter. */
export function ResultScreen({
  mission,
  stars,
  earnedXp,
  totalXp,
  firstTry,
  nextMission,
  onRetry,
}: {
  mission: Mission;
  stars: number;
  earnedXp: number;
  totalXp: number;
  firstTry: boolean;
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

      <div className="text-sm text-muted">
        Gesamt: <span className="font-semibold text-text">{totalXp} XP</span>
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
