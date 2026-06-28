"use client";

/**
 * Minimale Sound-Effekte über die Web Audio API — keine Audiodateien nötig.
 * Töne werden live aus Oszillatoren erzeugt. Standardmäßig an, per Schalter
 * abschaltbar (im Profil); die Einstellung liegt lokal.
 */

const KEY = "ua.sound";

type SoundKind = "success" | "wrong" | "complete" | "click";

let ctx: AudioContext | null = null;

function getCtx(): AudioContext | null {
  if (typeof window === "undefined") return null;
  const AC =
    window.AudioContext ??
    (window as unknown as { webkitAudioContext?: typeof AudioContext })
      .webkitAudioContext;
  if (!AC) return null;
  ctx ??= new AC();
  return ctx;
}

export function isSoundOn(): boolean {
  if (typeof localStorage === "undefined") return true;
  return localStorage.getItem(KEY) !== "off";
}

export function setSoundOn(on: boolean): void {
  if (typeof localStorage === "undefined") return;
  localStorage.setItem(KEY, on ? "on" : "off");
}

function tone(
  ac: AudioContext,
  freq: number,
  start: number,
  dur: number,
  type: OscillatorType = "sine",
  peak = 0.06,
): void {
  const osc = ac.createOscillator();
  const gain = ac.createGain();
  osc.type = type;
  osc.frequency.value = freq;
  gain.gain.setValueAtTime(0, start);
  gain.gain.linearRampToValueAtTime(peak, start + 0.01);
  gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
  osc.connect(gain).connect(ac.destination);
  osc.start(start);
  osc.stop(start + dur + 0.02);
}

/** Spielt einen kurzen Feedback-Sound (no-op, wenn ausgeschaltet). */
export function playSound(kind: SoundKind): void {
  if (!isSoundOn()) return;
  const ac = getCtx();
  if (!ac) return;
  if (ac.state === "suspended") void ac.resume();
  const t = ac.currentTime;
  switch (kind) {
    case "success":
      tone(ac, 660, t, 0.1);
      tone(ac, 988, t + 0.08, 0.14);
      break;
    case "wrong":
      tone(ac, 200, t, 0.2, "sawtooth", 0.05);
      break;
    case "complete":
      [523, 659, 784, 1047].forEach((f, i) =>
        tone(ac, f, t + i * 0.1, 0.18, "triangle", 0.07),
      );
      break;
    case "click":
      tone(ac, 440, t, 0.05, "square", 0.03);
      break;
  }
}
