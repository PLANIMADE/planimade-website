"use client";

import { useState } from "react";
import { cn } from "@/lib/cn";
import type { QuizStep } from "../types";

/** Multiple-Choice-Step: eine Antwort wählen, sofortiges Feedback. */
export function QuizStepView({
  step,
  onSolved,
  onWrong,
}: {
  step: QuizStep;
  onSolved: () => void;
  onWrong: () => void;
}) {
  const [picked, setPicked] = useState<number | null>(null);
  const solved = picked === step.answer;

  const choose = (i: number) => {
    if (solved) return; // bereits korrekt – sperren
    setPicked(i);
    if (i === step.answer) onSolved();
    else onWrong();
  };

  return (
    <div className="mx-auto flex max-w-xl flex-col gap-5">
      <h2 className="text-xl font-bold text-text">{step.title}</h2>
      <p className="text-balance leading-relaxed text-muted">{step.question}</p>

      <div className="flex flex-col gap-2.5">
        {step.options.map((opt, i) => {
          const isPicked = picked === i;
          const isCorrect = i === step.answer;
          const showCorrect = isPicked && isCorrect;
          const showWrong = isPicked && !isCorrect;
          return (
            <button
              key={i}
              onClick={() => choose(i)}
              disabled={solved}
              className={cn(
                "flex items-center justify-between rounded-xl border px-4 py-3 text-left text-sm transition",
                "border-border bg-surface text-text hover:border-accent",
                showCorrect && "border-success bg-success/15 text-success",
                showWrong && "border-danger bg-danger/15 text-danger",
                solved && !isCorrect && "opacity-50",
              )}
            >
              <span>{opt}</span>
              {showCorrect && <span aria-hidden>✓</span>}
              {showWrong && <span aria-hidden>✗</span>}
            </button>
          );
        })}
      </div>

      {picked !== null && !solved && (
        <p className="text-sm text-danger">
          Nicht ganz — versuch eine andere Antwort.
        </p>
      )}

      {solved && step.explain && (
        <div className="rounded-xl border border-success/40 bg-success/10 p-4 text-sm leading-relaxed text-text">
          {step.explain}
        </div>
      )}
    </div>
  );
}
