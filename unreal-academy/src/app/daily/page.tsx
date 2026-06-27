"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { allMissions } from "@/data/missions";
import { selectDailyMission } from "@/features/daily/daily";
import { dayStr } from "@/features/progression/progression";
import { MissionPlayer } from "@/features/missions/MissionPlayer";
import type { Mission } from "@/features/missions/types";

export default function DailyPage() {
  // Tagesabhängig + localStorage → erst nach Mount bestimmen (kein SSR-Mismatch).
  const [mission, setMission] = useState<Mission | null>(null);
  useEffect(() => {
    setMission(selectDailyMission(dayStr(), allMissions) ?? null);
  }, []);

  if (!mission) {
    return (
      <main className="bp-grid flex min-h-dvh flex-col items-center justify-center gap-3 px-6 text-center">
        <span className="text-sm text-muted">Daily Challenge wird geladen …</span>
        <Link href="/learn" className="text-sm text-accent hover:underline">
          ← Zur Lernkarte
        </Link>
      </main>
    );
  }

  // Daily-Modus: +50 %-Bonus wird im Mission Player eingelöst.
  return <MissionPlayer key={mission.id} mission={mission} daily />;
}
