"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { allMissions } from "@/data/missions";
import { useProgress } from "@/features/progression/local";
import { dayStr } from "@/features/progression/progression";
import { selectDailyMission } from "./daily";
import type { Mission } from "@/features/missions/types";

const typeLabel: Record<string, string> = {
  build: "Bauen",
  debug: "Debug",
};

/** Banner auf der Lernkarte: heutige Daily Challenge mit +50 %-Bonus. */
export function DailyBanner() {
  const progress = useProgress();
  const [today, setToday] = useState<string | null>(null);
  const [mission, setMission] = useState<Mission | null>(null);

  useEffect(() => {
    const d = dayStr();
    setToday(d);
    setMission(selectDailyMission(d, allMissions) ?? null);
  }, []);

  if (!mission || !today) return null;
  const done = progress.dailyDoneDay === today;

  return (
    <Link
      href="/daily"
      className={`mb-6 flex items-center gap-3 rounded-2xl border p-4 transition hover:border-streak ${
        done
          ? "border-success/40 bg-success/5"
          : "border-streak/40 bg-streak/5"
      }`}
    >
      <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-streak/15 text-xl">
        {done ? "✓" : "🔥"}
      </span>
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <span className="text-xs font-semibold uppercase tracking-wider text-streak">
            Daily Challenge
          </span>
          <span className="rounded-full bg-surface-2 px-2 py-0.5 text-[10px] uppercase tracking-wider text-muted">
            {typeLabel[mission.steps[0].kind] ?? "Aufgabe"}
          </span>
        </div>
        <div className="truncate font-semibold text-text">{mission.title}</div>
        <div className="text-[11px] text-muted">
          {done ? "Heute erledigt — morgen wartet die nächste." : "+50 % XP-Bonus"}
        </div>
      </div>
      {!done && <span className="shrink-0 text-sm text-streak">▶</span>}
    </Link>
  );
}
