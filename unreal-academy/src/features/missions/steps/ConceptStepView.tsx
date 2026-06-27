"use client";

import { useEffect } from "react";
import type { ConceptStep } from "../types";

const pinDemo: [string, string][] = [
  ["Exec", "bg-pin-exec"],
  ["Bool", "bg-pin-bool"],
  ["Int", "bg-pin-int"],
  ["Float", "bg-pin-float"],
];

/** Erklär-Step: Text lesen, dann „Weiter". Gilt sofort als lösbar. */
export function ConceptStepView({
  step,
  onSolved,
}: {
  step: ConceptStep;
  onSolved: () => void;
}) {
  // Concept-Steps sind reine Lese-Steps -> sofort fortsetzbar.
  useEffect(() => {
    onSolved();
  }, [onSolved]);

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-5">
      {step.visual === "flow" && (
        <div className="flex items-center justify-center gap-2 rounded-xl border border-border bg-surface/40 py-6 text-xs text-muted">
          <span className="rounded-lg border border-pin-bool/40 bg-surface px-3 py-2 text-text">
            Event
          </span>
          <span className="h-0.5 w-8 bg-pin-exec" />
          <span className="rounded-lg border border-accent/40 bg-surface px-3 py-2 text-text">
            Branch
          </span>
          <span className="h-0.5 w-8 bg-pin-exec" />
          <span className="rounded-lg border border-accent-2/40 bg-surface px-3 py-2 text-text">
            Aktion
          </span>
        </div>
      )}

      {step.visual === "pins" && (
        <div className="flex flex-wrap items-center justify-center gap-2 rounded-xl border border-border bg-surface/40 py-6">
          {pinDemo.map(([label, color]) => (
            <span
              key={label}
              className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-2.5 py-1 text-xs text-muted"
            >
              <span className={`h-2.5 w-2.5 rounded-full ${color}`} />
              {label}
            </span>
          ))}
        </div>
      )}

      <h2 className="text-xl font-bold text-text">{step.title}</h2>
      <div className="flex flex-col gap-3">
        {step.body.map((p, i) => (
          <p key={i} className="text-balance leading-relaxed text-muted">
            {p}
          </p>
        ))}
      </div>
    </div>
  );
}
