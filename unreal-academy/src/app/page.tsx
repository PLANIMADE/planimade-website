import Link from "next/link";

/**
 * Startseite / Landing. Führt in die Lernkarte (Pfad A spielbar).
 * Onboarding („Wähle dein Ziel“) folgt im Progression-/Politur-Schritt.
 */
export default function HomePage() {
  return (
    <main className="bp-grid relative flex min-h-dvh flex-col items-center justify-center px-6 text-center">
      <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-border bg-surface/70 px-4 py-1.5 text-xs font-medium text-muted">
        <span className="h-2 w-2 animate-pin-glow rounded-full bg-accent" />
        Pfad A spielbar — Mission Player live
      </span>

      <h1 className="max-w-2xl bg-gradient-to-br from-text to-accent bg-clip-text text-4xl font-extrabold leading-tight text-transparent sm:text-6xl">
        Unreal Academy
      </h1>
      <p className="mt-4 max-w-md text-balance text-muted sm:text-lg">
        Duolingo für Unreal Engine. Lerne, indem du Blueprints{" "}
        <span className="text-text">baust</span> — nicht zuschaust.
      </p>

      {/* Design-Token-Demo: Blueprint-Pin-Farben */}
      <div className="mt-8 flex flex-wrap items-center justify-center gap-2">
        {[
          ["Exec", "bg-pin-exec"],
          ["Bool", "bg-pin-bool"],
          ["Int", "bg-pin-int"],
          ["Float", "bg-pin-float"],
          ["String", "bg-pin-string"],
          ["Object", "bg-pin-object"],
        ].map(([label, color]) => (
          <span
            key={label}
            className="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-2.5 py-1 text-xs text-muted"
          >
            <span className={`h-2.5 w-2.5 rounded-full ${color}`} />
            {label}
          </span>
        ))}
      </div>

      <div className="mt-10 flex flex-col items-center gap-3 sm:flex-row">
        <Link
          href="/learn"
          className="rounded-xl bg-accent px-6 py-3 font-semibold text-bg shadow-glow transition hover:brightness-110"
        >
          ▶ Loslegen — Pfad „Blueprint Basics“
        </Link>
        <Link
          href="/sandbox"
          className="rounded-xl border border-border bg-surface px-6 py-3 text-sm text-muted transition hover:text-text"
        >
          Freier Simulator
        </Link>
      </div>

      <span className="mt-6 text-xs text-muted">
        XP &amp; Sterne · Streaks · Skill-Trees — kommen in den nächsten Schritten
      </span>
    </main>
  );
}
