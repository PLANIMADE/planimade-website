"use client";

import { useMemo } from "react";
import { allNodeSpecs } from "@/engine";
import type { NodeSpec } from "@/engine";

/** Palette: gruppiert alle verfügbaren Nodes nach Kategorie zum Hinzufügen. */
export function Palette({
  onAdd,
  allowed,
}: {
  onAdd: (spec: NodeSpec) => void;
  /** Optional: nur diese Node-Typen erlauben (pro Mission konfigurierbar). */
  allowed?: string[];
}) {
  const groups = useMemo(() => {
    const specs = allNodeSpecs().filter(
      (s) => !allowed || allowed.includes(s.type),
    );
    const map = new Map<string, NodeSpec[]>();
    for (const s of specs) {
      const arr = map.get(s.category) ?? [];
      arr.push(s);
      map.set(s.category, arr);
    }
    return [...map.entries()];
  }, [allowed]);

  return (
    <div className="flex h-full flex-col gap-3 overflow-auto">
      <div className="text-xs font-semibold uppercase tracking-wide text-muted">
        Nodes
      </div>
      {groups.map(([category, specs]) => (
        <div key={category} className="flex flex-col gap-1.5">
          <div className="text-[10px] uppercase tracking-wider text-muted/70">
            {category}
          </div>
          {specs.map((spec) => (
            <button
              key={spec.type}
              onClick={() => onAdd(spec)}
              className="rounded-lg border border-border bg-surface px-2.5 py-1.5 text-left text-xs text-text transition hover:border-accent hover:bg-surface-2"
            >
              + {spec.title}
            </button>
          ))}
        </div>
      ))}
    </div>
  );
}
