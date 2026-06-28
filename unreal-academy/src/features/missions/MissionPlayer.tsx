"use client";

import { useCallback, useMemo, useState } from "react";
import Link from "next/link";
import { getNextMission } from "@/data/missions";
import {
  claimDaily,
  recordCompletion,
  type CompletionResult,
} from "@/features/progression/local";
import { DAILY_BONUS } from "@/features/daily/daily";
import { playSound } from "@/features/sound/sound";
import { ResultScreen } from "./ResultScreen";
import { ConceptStepView } from "./steps/ConceptStepView";
import { QuizStepView } from "./steps/QuizStepView";
import { BuilderStepView } from "./steps/BuilderStepView";
import { STEP_XP, type Mission } from "./types";

/** Berechnet Sterne aus der Fehlerzahl über die ganze Mission. */
function starsFor(mistakes: number): number {
  if (mistakes === 0) return 3;
  if (mistakes <= 2) return 2;
  return 1;
}

/**
 * Mission Player — führt durch die Step-Sequenz einer Mission.
 * concept → build/debug/quiz → … → Ergebnis-Screen mit XP.
 */
export function MissionPlayer({
  mission,
  daily = false,
}: {
  mission: Mission;
  /** Im Daily-Modus gibt es zusätzlich den +50 %-Tagesbonus (1×/Tag). */
  daily?: boolean;
}) {
  const [index, setIndex] = useState(0);
  const [solved, setSolved] = useState(false);
  const [mistakes, setMistakes] = useState(0);
  const [finished, setFinished] = useState(false);
  const [result, setResult] = useState<CompletionResult | null>(null);
  const [dailyBonus, setDailyBonus] = useState(0);
  const [finalXp, setFinalXp] = useState(0);

  const step = mission.steps[index];
  const isLast = index === mission.steps.length - 1;

  const onSolved = useCallback(() => setSolved(true), []);
  const onWrong = useCallback(() => setMistakes((m) => m + 1), []);

  const earned = useMemo(() => {
    const stepXp = mission.steps.reduce((sum, s) => sum + STEP_XP[s.kind], 0);
    const base = mission.xp + stepXp;
    const firstTry = mistakes === 0;
    const bonus = firstTry ? Math.round(base * 0.2) : 0;
    return { base, bonus, total: base + bonus, firstTry };
  }, [mission, mistakes]);

  const advance = useCallback(() => {
    if (!isLast) {
      setIndex((i) => i + 1);
      setSolved(false);
      return;
    }
    const stars = starsFor(mistakes);
    const completion = recordCompletion(
      mission.id,
      stars,
      earned.total,
      earned.firstTry,
    );
    setResult(completion);

    // Daily-Bonus (einmal pro Tag) zusätzlich einlösen.
    let total = completion.progress.xp;
    if (daily) {
      const claim = claimDaily(Math.round(earned.base * DAILY_BONUS));
      setDailyBonus(claim.bonus);
      total = claim.progress.xp;
    }
    setFinalXp(total);
    setFinished(true);
    playSound("complete");
  }, [isLast, mistakes, mission.id, earned, daily]);

  const restart = useCallback(() => {
    setIndex(0);
    setSolved(false);
    setMistakes(0);
    setFinished(false);
  }, []);

  if (finished && result) {
    return (
      <ResultScreen
        mission={mission}
        stars={starsFor(mistakes)}
        earnedXp={earned.total}
        dailyBonus={dailyBonus}
        totalXp={finalXp}
        firstTry={earned.firstTry}
        streak={result.progress.streak.count}
        rankedUp={result.rankedUp}
        rankTitle={result.rank.rank.title}
        newBadges={result.newBadges}
        nextMission={daily ? null : getNextMission(mission.id) ?? null}
        onRetry={restart}
      />
    );
  }

  return (
    <div className="flex h-dvh flex-col">
      {/* Kopf: Fortschritt + Abbrechen */}
      <header className="flex items-center gap-3 border-b border-border px-4 py-3">
        <Link
          href="/learn"
          className="text-muted transition hover:text-text"
          aria-label="Mission verlassen"
        >
          ✕
        </Link>
        <div className="flex flex-1 items-center gap-1.5">
          {mission.steps.map((_, i) => (
            <span
              key={i}
              className={`h-1.5 flex-1 rounded-full transition ${
                i < index
                  ? "bg-success"
                  : i === index
                    ? "bg-accent"
                    : "bg-border"
              }`}
            />
          ))}
        </div>
        <span className="shrink-0 font-mono text-[10px] uppercase tracking-wider text-muted">
          {mission.id} · {index + 1}/{mission.steps.length}
        </span>
      </header>

      {/* Step-Inhalt */}
      <main className="min-h-0 flex-1 overflow-auto p-4">
        {step.kind === "concept" && (
          <ConceptStepView key={index} step={step} onSolved={onSolved} />
        )}
        {step.kind === "quiz" && (
          <QuizStepView
            key={index}
            step={step}
            onSolved={onSolved}
            onWrong={onWrong}
          />
        )}
        {(step.kind === "build" || step.kind === "debug") && (
          <BuilderStepView
            key={index}
            step={step}
            onSolved={onSolved}
            onWrong={onWrong}
          />
        )}
      </main>

      {/* Fuß: Weiter */}
      <footer className="border-t border-border p-4">
        <button
          onClick={advance}
          disabled={!solved}
          className={`w-full rounded-xl px-6 py-3.5 font-semibold transition ${
            solved
              ? "bg-accent text-bg shadow-glow hover:brightness-110"
              : "cursor-not-allowed bg-surface text-muted"
          }`}
        >
          {isLast ? "Abschließen" : "Weiter"}
        </button>
      </footer>
    </div>
  );
}
