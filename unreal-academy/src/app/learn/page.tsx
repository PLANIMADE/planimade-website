"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { tracks } from "@/data/missions";
import {
  loadProgress,
  type LocalProgress,
} from "@/features/progression/local";
import type { MissionType } from "@/features/missions/types";

const typeIcon: Record<MissionType, string> = {
  concept: "📘",
  build: "🔧",
  quiz: "❓",
  debug: "🐛",
  boss: "👑",
};

const typeLabel: Record<MissionType, string> = {
  concept: "Konzept",
  build: "Bauen",
  quiz: "Quiz",
  debug: "Debug",
  boss: "Prüfung",
};

export default function LearnPage() {
  // Fortschritt erst nach Mount lesen (localStorage ist client-only).
  const [progress, setProgress] = useState<LocalProgress | null>(null);
  useEffect(() => setProgress(loadProgress()), []);

  const completed = progress?.completed ?? {};
  const totalXp = progress?.xp ?? 0;

  return (
    <main className="bp-grid min-h-dvh px-4 pb-24 pt-6">
      <div className="mx-auto max-w-md">
        {/* Kopf: XP + zurück */}
        <header className="mb-8 flex items-center justify-between">
          <Link href="/" className="text-sm text-muted hover:text-text">
            ← Start
          </Link>
          <div className="flex items-center gap-1.5 rounded-full border border-border bg-surface/70 px-3 py-1.5 text-sm">
            <span className="text-xp">★</span>
            <span className="font-semibold text-text">{totalXp}</span>
            <span className="text-xs text-muted">XP</span>
          </div>
        </header>

        {tracks.map((track) => {
          const done = track.missions.filter((m) => completed[m.id]).length;
          const pct = Math.round((done / track.missions.length) * 100);
          return (
            <section key={track.id} className="mb-10">
              <div className="mb-4">
                <h1 className="text-xl font-extrabold text-text">{track.title}</h1>
                <p className="text-sm text-muted">{track.subtitle}</p>
                <div className="mt-3 h-2 overflow-hidden rounded-full bg-border">
                  <div
                    className="h-full rounded-full bg-accent transition-all"
                    style={{ width: `${pct}%` }}
                  />
                </div>
                <div className="mt-1 text-right text-[11px] text-muted">
                  {done}/{track.missions.length} Missionen
                </div>
              </div>

              {/* Vertikale Lernkarte */}
              <ol className="flex flex-col gap-3">
                {track.missions.map((m) => {
                  const stars = completed[m.id] ?? 0;
                  const isDone = stars > 0;
                  return (
                    <li key={m.id}>
                      <Link
                        href={`/mission/${m.id}`}
                        className={`flex items-center gap-3 rounded-2xl border p-4 transition hover:border-accent hover:bg-surface-2 ${
                          isDone
                            ? "border-success/40 bg-success/5"
                            : "border-border bg-surface/50"
                        }`}
                      >
                        <span
                          className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-xl ${
                            m.type === "boss"
                              ? "bg-accent-2/20"
                              : "bg-surface-2"
                          }`}
                        >
                          {isDone ? "✓" : typeIcon[m.type]}
                        </span>
                        <div className="min-w-0 flex-1">
                          <div className="flex items-center gap-2">
                            <span className="font-mono text-[10px] text-muted">
                              {m.id}
                            </span>
                            <span className="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] uppercase tracking-wider text-muted">
                              {typeLabel[m.type]}
                            </span>
                          </div>
                          <div className="truncate font-semibold text-text">
                            {m.title}
                          </div>
                        </div>
                        <div className="flex shrink-0 flex-col items-end">
                          <span className="text-xs font-semibold text-xp">
                            +{m.xp}
                          </span>
                          {isDone && (
                            <span className="text-xs text-xp" aria-hidden>
                              {"★".repeat(stars)}
                            </span>
                          )}
                        </div>
                      </Link>
                    </li>
                  );
                })}
              </ol>
            </section>
          );
        })}

        <p className="text-center text-xs text-muted">
          Weitere Pfade — Gameplay Systems &amp; Debugging Lab — folgen.
        </p>
      </div>
    </main>
  );
}
