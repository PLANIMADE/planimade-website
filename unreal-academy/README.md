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
| **Phase 3** | Technische Umsetzung (Setup → Engine → Simulator → Mission Player → Progression) | 🚧 läuft — Setup ✅ · Graph-Engine ✅ · Simulator ✅ · Mission Player + Pfad A ✅ |

### Spielbar (lokaler Stand)

- `/` — Landing → Einstieg in die Lernkarte
- `/learn` — vertikale Lernkarte (Pfad A „Blueprint Basics", 8 Missionen) mit XP & Sternen (lokal gespeichert)
- `/mission/[id]` — Mission Player: Step-Sequenz (concept · build · debug · quiz · boss) mit verhaltensbasierter Bewertung & Ergebnis-Screen
- `/sandbox` — freier Blueprint-Simulator (Tür-Mission)

**Nächste Schritte:** volle Progression (Streak, Ränge, Badges, Supabase-Sync) · Content-Seed Pfade B & C · Daily Challenge · Onboarding & Mobile-Politur.

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
