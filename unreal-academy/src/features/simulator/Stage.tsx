"use client";

import type { EmittedAction } from "@/engine";
import { cn } from "@/lib/cn";

/**
 * Visuelle „Bühne": macht die Simulation sichtbar. Reagiert auf die vom Lauf
 * ausgelösten Aktionen (Tür öffnet, Lampe leuchtet, Konsole printet …).
 */
export function Stage({
  actions,
  hasRun,
}: {
  actions: EmittedAction[];
  hasRun: boolean;
}) {
  const kinds = actions.map((a) => a.kind);
  const doorOpen = kinds.includes("OpenDoor");
  const lightOn = kinds.filter((k) => k === "ToggleLight").length % 2 === 1;
  const dead = kinds.includes("SetDead");
  const prints = actions
    .filter((a) => a.kind === "Print")
    .map((a) => String(a.payload?.text ?? ""));

  return (
    <div className="flex h-full flex-col gap-3">
      <div className="text-xs font-semibold uppercase tracking-wide text-muted">
        Bühne
      </div>

      <div className="bp-grid relative flex flex-1 items-center justify-center gap-8 rounded-xl border border-border bg-surface/60 p-6">
        {/* Tür */}
        <div className="flex flex-col items-center gap-2">
          <div
            className={cn(
              "relative h-24 w-16 rounded-md border-2 transition-all duration-500",
              doorOpen
                ? "border-success bg-success/10 [transform:perspective(300px)_rotateY(-55deg)]"
                : "border-muted bg-surface-2",
            )}
          >
            <span className="absolute right-1 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-xp" />
          </div>
          <span className="text-[11px] text-muted">
            {doorOpen ? "offen" : "zu"}
          </span>
        </div>

        {/* Lampe */}
        <div className="flex flex-col items-center gap-2">
          <div
            className={cn(
              "h-14 w-14 rounded-full transition-all duration-300",
              lightOn
                ? "bg-xp shadow-[0_0_28px_8px_rgb(var(--xp)/0.7)]"
                : "border-2 border-muted bg-surface-2",
            )}
          />
          <span className="text-[11px] text-muted">
            {lightOn ? "an" : "aus"}
          </span>
        </div>

        {/* Figur (Health/Dead) */}
        <div className="flex flex-col items-center gap-2">
          <div
            className={cn(
              "text-4xl transition-all duration-300",
              dead ? "rotate-90 opacity-40 grayscale" : "opacity-100",
            )}
          >
            🧍
          </div>
          <span className="text-[11px] text-muted">
            {dead ? "K.O." : "lebt"}
          </span>
        </div>
      </div>

      {/* Konsole */}
      <div className="h-20 overflow-auto rounded-xl border border-border bg-bg/80 p-2 font-mono text-[11px]">
        {!hasRun && <span className="text-muted">▶ Simulation noch nicht gestartet</span>}
        {hasRun && prints.length === 0 && (
          <span className="text-muted">— keine Print-Ausgabe —</span>
        )}
        {prints.map((p, i) => (
          <div key={i} className="text-pin-float">
            ▸ {p}
          </div>
        ))}
      </div>
    </div>
  );
}
