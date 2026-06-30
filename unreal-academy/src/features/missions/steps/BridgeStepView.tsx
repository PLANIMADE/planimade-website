"use client";

import { useState } from "react";
import type { BridgeStep } from "../types";

/**
 * Brücke zum echten Editor: zeigt eine Schritt-für-Schritt-Anleitung zum
 * Nachbauen in der echten Unreal Engine. Bestätigen (oder „später") gibt frei.
 */
export function BridgeStepView({
  step,
  onSolved,
}: {
  step: BridgeStep;
  onSolved: () => void;
}) {
  const [done, setDone] = useState(false);

  const confirm = () => {
    setDone(true);
    onSolved();
  };

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-5">
      <div className="flex items-center gap-2">
        <span className="rounded-full border border-accent-2/40 bg-accent-2/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-accent-2">
          Im echten Unreal
        </span>
      </div>

      <h2 className="text-xl font-bold text-text">{step.title}</h2>
      <p className="text-balance leading-relaxed text-muted">{step.intro}</p>

      <ol className="flex flex-col gap-3">
        {step.steps.map((s, i) => (
          <li key={i} className="flex gap-3">
            <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-2 text-xs font-semibold text-accent">
              {i + 1}
            </span>
            <span className="pt-0.5 text-sm leading-relaxed text-text">{s}</span>
          </li>
        ))}
      </ol>

      <div className="rounded-xl border border-success/30 bg-success/5 p-4 text-sm leading-relaxed text-text">
        <span className="font-semibold text-success">✓ Ergebnis:</span>{" "}
        {step.result}
      </div>

      {!done ? (
        <div className="flex flex-col gap-2">
          <button
            onClick={confirm}
            className="rounded-xl bg-accent px-6 py-3 font-semibold text-bg shadow-glow transition hover:brightness-110"
          >
            Hab ich im Editor gebaut ✓
          </button>
          <button
            onClick={confirm}
            className="rounded-xl px-6 py-2 text-sm text-muted transition hover:text-text"
          >
            Mache ich später — erstmal weiter
          </button>
        </div>
      ) : (
        <div className="rounded-xl border border-border bg-surface/50 px-6 py-3 text-center text-sm text-success">
          Stark — genau so entsteht Selbstständigkeit. 🚀
        </div>
      )}
    </div>
  );
}
