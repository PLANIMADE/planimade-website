# Unreal Academy 🎮

> **„Duolingo für Unreal Engine."** — Lerne Unreal spielerisch, indem du
> Systeme *baust* statt Videos zu schauen.

Eine mobile- und desktopfähige Lernplattform mit interaktivem Blueprint-Simulator,
XP/Streaks/Rängen, Skill-Trees und missionsbasiertem Lernen.

## Status

| Phase | Inhalt | Stand |
|---|---|---|
| **Phase 1** | Architektur, Datenmodelle, Tech-Stack, UX, Gamification, Simulator-Design | ✅ → [`docs/PHASE-1-ARCHITECTURE.md`](docs/PHASE-1-ARCHITECTURE.md) |
| **Phase 2** | MVP-Scope (3 Pfade, 24 Missionen, Simulator, Daily Challenge) | ✅ → [`docs/PHASE-2-MVP.md`](docs/PHASE-2-MVP.md) |
| **Phase 3** | Technische Umsetzung (Setup → Engine → Simulator → Mission Player → Progression → Content → Daily → Onboarding) | 🚧 läuft — Setup ✅ · Graph-Engine ✅ · Simulator ✅ · Mission Player ✅ · Progression (XP/Streak/Ränge/Badges) ✅ · Content 3 Pfade / 24 Missionen ✅ · Daily Challenge ✅ · Onboarding & Mobile-Politur ✅ |

### Spielbar (lokaler Stand)

- `/` — Landing → Einstieg in die Lernkarte
- `/onboarding` — „Wähle dein Ziel": Pfad-Auswahl beim ersten Start (neue Nutzer werden automatisch hierher geleitet)
- `/learn` — vertikale Lernkarte über **3 Pfade & 24 Missionen** (A „Blueprint Basics", B „Gameplay Systems", C „Debugging Lab") mit XP, Streak & Sternen (lokal gespeichert)
- `/mission/[id]` — Mission Player: Step-Sequenz (concept · build · debug · quiz · boss) mit verhaltensbasierter Bewertung, XP/Streak/Rang-Up & neuen Badges im Ergebnis-Screen
- `/daily` — Daily Challenge: täglich rotierende Bau-/Debug-Mission mit **+50 % XP** (deterministisch, 1×/Tag einlösbar)
- `/profile` — Rang-Fortschritt, Streak, Abzeichen-Galerie & Pfad-Fortschritt
- `/sandbox` — freier Blueprint-Simulator (Tür-Mission)

Gamification: 5 Ränge (XP-Schwellen), Tages-Streak mit Flamme, 5 Badges (Erster Blueprint, Bug-Jäger, 3-Tage-Streak, Erst-Versuch-Profi, Pfad-Meister). Persistenz lokal (Gast/Offline); Supabase-Sync folgt.

Alle 24 Missionen sind testabgesichert: Jeder Bau-/Debug-Startgraph schlägt zunächst fehl, und für jede existiert eine Referenzlösung, die den Grader besteht (64 Tests gesamt).

Mobile: Simulator mit Pinch-Zoom und aufklappbarer Node-Palette (Bottom-Sheet), Bottom-Nav (Lernen · Täglich · Profil).

**Nächste Schritte:** Supabase-Auth/Sync (echtes Konto + Cloud-Speicher statt nur lokal).

**Nächste Schritte:** Content-Seed Pfade B & C · Daily Challenge · Supabase-Auth/Sync · Onboarding & Mobile-Politur.

## Lokal starten

```bash
cd unreal-academy
npm install
npm run dev      # http://localhost:3000
```

Weitere Skripte: `npm run build`, `npm run typecheck`, `npm run lint`, `npm test`.

## Tech-Stack (Kurzfassung)

Next.js 14 · TypeScript · Tailwind · Framer Motion · Zustand · TanStack Query ·
React Flow (+ eigene UI-freie Graph-Engine) · Supabase (Postgres/Auth/RLS).

Details & Begründung: siehe Phase-1-Dokument.
