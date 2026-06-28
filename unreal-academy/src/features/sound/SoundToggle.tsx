"use client";

import { useEffect, useState } from "react";
import { isSoundOn, playSound, setSoundOn } from "./sound";

/** Ein-/Ausschalter für Sound-Effekte (Einstellung liegt lokal). */
export function SoundToggle() {
  const [on, setOn] = useState(true);
  // Erst nach Mount lesen (localStorage ist client-only).
  useEffect(() => setOn(isSoundOn()), []);

  const toggle = () => {
    const next = !on;
    setOn(next);
    setSoundOn(next);
    if (next) playSound("click");
  };

  return (
    <button
      onClick={toggle}
      className="flex w-full items-center justify-between rounded-2xl border border-border bg-surface/40 p-4 text-left transition hover:border-accent"
      aria-pressed={on}
    >
      <span className="flex items-center gap-3">
        <span className="text-xl" aria-hidden>
          {on ? "🔊" : "🔇"}
        </span>
        <span className="text-sm font-semibold text-text">Sound-Effekte</span>
      </span>
      <span
        className={`relative h-6 w-11 rounded-full transition ${
          on ? "bg-accent" : "bg-border"
        }`}
      >
        <span
          className={`absolute top-0.5 h-5 w-5 rounded-full bg-bg transition-all ${
            on ? "left-[22px]" : "left-0.5"
          }`}
        />
      </span>
    </button>
  );
}
