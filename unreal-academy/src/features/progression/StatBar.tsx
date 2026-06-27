"use client";

import { useProgress } from "./local";
import { computeRank, isStreakAlive } from "./progression";

/**
 * Kompakte Statusleiste: XP und Streak — liest reaktiv aus dem Store.
 * Vor dem Mount (SSR) zeigt sie den Leerzustand, danach den echten Fortschritt.
 */
export function StatBar() {
  const p = useProgress();
  const alive = isStreakAlive(p.streak);

  return (
    <div className="flex items-center gap-2">
      <div className="flex items-center gap-1.5 rounded-full border border-border bg-surface/70 px-3 py-1.5 text-sm">
        <span className="text-xp" aria-hidden>★</span>
        <span className="font-semibold text-text">{p.xp}</span>
        <span className="text-xs text-muted">XP</span>
      </div>
      <div
        className="flex items-center gap-1.5 rounded-full border border-border bg-surface/70 px-3 py-1.5 text-sm"
        title={`Streak: ${p.streak.count} Tag(e)`}
      >
        <span className={alive ? "text-streak" : "opacity-40"} aria-hidden>
          🔥
        </span>
        <span className="font-semibold text-text">
          {alive ? p.streak.count : 0}
        </span>
      </div>
    </div>
  );
}

/** Nur die Rang-Zeile (z.B. für den Profil-Kopf). */
export function RankBadge() {
  const p = useProgress();
  const rank = computeRank(p.xp);
  return (
    <span className="rounded-full border border-accent/40 bg-accent/10 px-3 py-1 text-xs font-medium text-accent">
      {rank.rank.title}
    </span>
  );
}
