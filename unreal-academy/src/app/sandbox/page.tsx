import Link from "next/link";
import { Simulator } from "@/features/simulator/Simulator";
import { doorScenario } from "@/data/scenarios";

/**
 * Sandbox-/Simulator-Demo. Lädt die Tür-Mission vollständig verdrahtet:
 * ▶ Simulieren öffnet die Tür (HasKey = true). Variable im Get-Node anpassbar,
 * Nodes aus der Palette hinzufügbar.
 */
export default function SandboxPage() {
  return (
    <main className="flex h-dvh flex-col gap-3 p-3">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-lg font-bold">Blueprint-Simulator</h1>
          <p className="text-xs text-muted">
            Mission: „Öffne die Tür nur mit Schlüssel“ — drücke ▶ Simulieren.
          </p>
        </div>
        <Link href="/" className="text-xs text-accent hover:underline">
          ← Start
        </Link>
      </header>

      <div className="min-h-0 flex-1">
        <Simulator initialGraph={doorScenario} />
      </div>
    </main>
  );
}
