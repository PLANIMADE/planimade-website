"use client";

import { useCallback, useState } from "react";
import { gradeGraph, type BPGraph, type GraderResult } from "@/engine";
import { Simulator } from "@/features/simulator/Simulator";
import type { BuilderStep } from "../types";

/**
 * Bau-/Debug-Step: Simulator + verhaltensbasierte Bewertung.
 * Bei jedem ▶ Simulieren wird der aktuelle Graph gegen die Mission-Testfälle
 * geprüft. Lösung = alle Fälle bestehen.
 */
export function BuilderStepView({
  step,
  onSolved,
  onWrong,
}: {
  step: BuilderStep;
  onSolved: () => void;
  onWrong: () => void;
}) {
  const [result, setResult] = useState<GraderResult | null>(null);
  const [solved, setSolved] = useState(false);
  const [showHint, setShowHint] = useState(false);

  const handleRun = useCallback(
    (graph: BPGraph) => {
      const r = gradeGraph(graph, step.grader);
      setResult(r);
      if (r.passed && !solved) {
        setSolved(true);
        onSolved();
      } else if (!r.passed) {
        onWrong();
        if (step.hint) setShowHint(true);
      }
    },
    [step.grader, step.hint, solved, onSolved, onWrong],
  );

  return (
    <div className="flex h-full flex-col gap-3">
      <div className="flex flex-col gap-1">
        <h2 className="text-lg font-bold text-text">{step.title}</h2>
        <p className="text-sm leading-relaxed text-muted">{step.brief}</p>
        <p className="text-xs text-muted">
          <span className="text-accent">Ziel:</span> {step.goal}
        </p>
      </div>

      {/* Simulator füllt den verfügbaren Raum. */}
      <div className="min-h-[420px] flex-1">
        <Simulator
          initialGraph={step.initialGraph}
          allowedNodes={step.allowedNodes}
          onRun={handleRun}
        />
      </div>

      {/* Bewertungs-Feedback */}
      {result && (
        <div
          className={`rounded-xl border p-3 text-sm ${
            result.passed
              ? "border-success/50 bg-success/10 text-success"
              : "border-danger/40 bg-danger/10 text-text"
          }`}
        >
          {result.passed ? (
            <span className="font-semibold">✓ Gelöst! Alle Testfälle bestanden.</span>
          ) : (
            <div className="flex flex-col gap-1">
              <span className="font-semibold text-danger">Noch nicht ganz:</span>
              <ul className="flex flex-col gap-0.5">
                {result.cases.map((c, i) => (
                  <li key={i} className="flex items-start gap-2 text-xs text-muted">
                    <span className={c.passed ? "text-success" : "text-danger"}>
                      {c.passed ? "✓" : "✗"}
                    </span>
                    <span>
                      {c.name}
                      {c.detail ? ` — ${c.detail}` : ""}
                    </span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      {showHint && step.hint && !solved && (
        <div className="rounded-xl border border-accent/40 bg-accent/10 p-3 text-sm text-text">
          💡 {step.hint}
        </div>
      )}
    </div>
  );
}
