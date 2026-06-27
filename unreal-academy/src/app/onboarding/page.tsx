"use client";

import { useRouter } from "next/navigation";
import { tracks } from "@/data/missions";
import { setOnboarding } from "@/features/progression/local";

const trackIcon: Record<string, string> = { A: "🧩", B: "🎮", C: "🐛" };

const trackPitch: Record<string, string> = {
  A: "Verstehe die Grundlagen: Events, Variablen und Entscheidungen.",
  B: "Baue echte Systeme: Zähler, Lebenspunkte und Trigger.",
  C: "Werde zum Bug-Jäger: Fehler erkennen und reparieren.",
};

export default function OnboardingPage() {
  const router = useRouter();

  const choose = (goal: string | null) => {
    setOnboarding(goal);
    router.push("/learn");
  };

  return (
    <main className="bp-grid flex min-h-dvh flex-col items-center justify-center px-5 py-10">
      <div className="mx-auto w-full max-w-md">
        <div className="mb-8 text-center">
          <span className="mb-3 inline-flex items-center gap-2 rounded-full border border-border bg-surface/70 px-4 py-1.5 text-xs font-medium text-muted">
            <span className="h-2 w-2 animate-pin-glow rounded-full bg-accent" />
            Willkommen bei Unreal Academy
          </span>
          <h1 className="text-2xl font-extrabold text-text sm:text-3xl">
            Wähle dein Ziel
          </h1>
          <p className="mt-2 text-balance text-sm text-muted">
            Womit möchtest du starten? Du kannst später jederzeit wechseln.
          </p>
        </div>

        <div className="flex flex-col gap-3">
          {tracks.map((t) => (
            <button
              key={t.id}
              onClick={() => choose(t.id)}
              className="group flex items-center gap-4 rounded-2xl border border-border bg-surface/50 p-4 text-left transition hover:border-accent hover:bg-surface-2"
            >
              <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-surface-2 text-2xl">
                {trackIcon[t.id] ?? "▶"}
              </span>
              <div className="min-w-0 flex-1">
                <div className="font-semibold text-text">{t.title}</div>
                <div className="text-xs leading-snug text-muted">
                  {trackPitch[t.id] ?? t.subtitle}
                </div>
              </div>
              <span className="shrink-0 text-muted transition group-hover:text-accent">
                →
              </span>
            </button>
          ))}
        </div>

        <button
          onClick={() => choose(null)}
          className="mt-6 w-full rounded-xl px-6 py-2.5 text-sm text-muted transition hover:text-text"
        >
          Erst mal stöbern
        </button>
      </div>
    </main>
  );
}
