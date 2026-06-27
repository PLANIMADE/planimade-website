"use client";

import Link from "next/link";
import { tracks } from "@/data/missions";
import { useProgress } from "@/features/progression/local";
import {
  BADGES,
  computeRank,
  isStreakAlive,
} from "@/features/progression/progression";
import { AuthPanel } from "@/features/auth/AuthPanel";
import { BottomNav } from "@/components/BottomNav";

export default function ProfilePage() {
  const p = useProgress();
  const rank = computeRank(p.xp);
  const alive = isStreakAlive(p.streak);
  const earned = new Set(p.badges);
  const completedCount = Object.keys(p.completed).length;

  return (
    <main className="bp-grid min-h-dvh px-4 pb-24 pt-6">
      <div className="mx-auto max-w-md">
        <header className="mb-6 flex items-center justify-between">
          <h1 className="text-xl font-extrabold text-text">Profil</h1>
          <Link href="/learn" className="text-sm text-muted hover:text-text">
            Lernkarte →
          </Link>
        </header>

        {/* Rang-Karte */}
        <section className="mb-6 rounded-2xl border border-border bg-surface/50 p-5">
          <div className="flex items-baseline justify-between">
            <span className="text-lg font-bold text-text">{rank.rank.title}</span>
            <span className="text-sm text-muted">
              <span className="font-semibold text-xp">{p.xp}</span> XP
            </span>
          </div>

          <div className="mt-3 h-2.5 overflow-hidden rounded-full bg-border">
            <div
              className="h-full rounded-full bg-accent transition-all"
              style={{ width: `${Math.round(rank.progress * 100)}%` }}
            />
          </div>
          <div className="mt-1.5 text-right text-[11px] text-muted">
            {rank.next
              ? `Noch ${rank.toNext} XP bis „${rank.next.title}"`
              : "Höchster Rang erreicht 🏆"}
          </div>
        </section>

        {/* Konto / Cloud-Sync */}
        <section className="mb-6">
          <AuthPanel />
        </section>

        {/* Kennzahlen */}
        <section className="mb-6 grid grid-cols-3 gap-3">
          <Stat label="Streak" value={alive ? `${p.streak.count}` : "0"} icon="🔥" />
          <Stat label="Missionen" value={`${completedCount}`} icon="✅" />
          <Stat label="Abzeichen" value={`${earned.size}`} icon="🏅" />
        </section>

        {/* Badges */}
        <section className="mb-6">
          <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">
            Abzeichen
          </h2>
          <div className="grid grid-cols-2 gap-3">
            {BADGES.map((b) => {
              const has = earned.has(b.id);
              return (
                <div
                  key={b.id}
                  className={`flex items-center gap-3 rounded-2xl border p-3 transition ${
                    has
                      ? "border-xp/40 bg-xp/5"
                      : "border-border bg-surface/30 opacity-60"
                  }`}
                >
                  <span
                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xl ${
                      has ? "bg-xp/15" : "bg-surface-2 grayscale"
                    }`}
                  >
                    {has ? b.icon : "🔒"}
                  </span>
                  <div className="min-w-0">
                    <div className="truncate text-sm font-semibold text-text">
                      {b.title}
                    </div>
                    <div className="text-[11px] leading-tight text-muted">
                      {b.desc}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </section>

        {/* Pfad-Fortschritt */}
        <section>
          <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">
            Pfade
          </h2>
          <div className="flex flex-col gap-3">
            {tracks.map((t) => {
              const done = t.missions.filter((m) => p.completed[m.id]).length;
              const pct = Math.round((done / t.missions.length) * 100);
              return (
                <Link
                  key={t.id}
                  href="/learn"
                  className="rounded-2xl border border-border bg-surface/50 p-4 transition hover:border-accent"
                >
                  <div className="flex items-center justify-between">
                    <span className="font-semibold text-text">{t.title}</span>
                    <span className="text-xs text-muted">
                      {done}/{t.missions.length}
                    </span>
                  </div>
                  <div className="mt-2 h-2 overflow-hidden rounded-full bg-border">
                    <div
                      className="h-full rounded-full bg-accent transition-all"
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                </Link>
              );
            })}
          </div>
        </section>
      </div>

      <BottomNav />
    </main>
  );
}

function Stat({
  label,
  value,
  icon,
}: {
  label: string;
  value: string;
  icon: string;
}) {
  return (
    <div className="flex flex-col items-center gap-1 rounded-2xl border border-border bg-surface/50 py-4">
      <span className="text-xl" aria-hidden>
        {icon}
      </span>
      <span className="text-lg font-bold text-text">{value}</span>
      <span className="text-[11px] uppercase tracking-wider text-muted">
        {label}
      </span>
    </div>
  );
}
