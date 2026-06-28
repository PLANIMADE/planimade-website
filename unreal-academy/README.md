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
| **Phase 3** | Technische Umsetzung (Setup → Engine → Simulator → Mission Player → Progression → Content → Daily → Onboarding → Cloud-Sync) | ✅ — Setup · Graph-Engine · Simulator · Mission Player · Progression (XP/Streak/Ränge/Badges) · Content 4 Pfade / 32 Missionen · Daily Challenge · Onboarding & Mobile-Politur · Supabase-Auth & Cloud-Sync (optional) · Intermediate-Mechaniken (ForLoop, Logik, Clamp …) |

### Spielbar (lokaler Stand)

- `/` — Landing → Einstieg in die Lernkarte
- `/onboarding` — „Wähle dein Ziel": Pfad-Auswahl beim ersten Start (neue Nutzer werden automatisch hierher geleitet)
- `/learn` — vertikale Lernkarte über **4 Pfade & 32 Missionen** (A „Blueprint Basics", B „Gameplay Systems", C „Debugging Lab", D „Fortgeschritten") mit XP, Streak & Sternen (lokal gespeichert)
- `/mission/[id]` — Mission Player: Step-Sequenz (concept · build · debug · quiz · boss) mit verhaltensbasierter Bewertung, XP/Streak/Rang-Up & neuen Badges im Ergebnis-Screen
- `/daily` — Daily Challenge: täglich rotierende Bau-/Debug-Mission mit **+50 % XP** (deterministisch, 1×/Tag einlösbar)
- `/profile` — Rang-Fortschritt, Streak, Abzeichen-Galerie, Pfad-Fortschritt & Konto/Cloud-Sync
- `/sandbox` — freier Blueprint-Simulator (Tür-Mission)

Gamification: 5 Ränge (XP-Schwellen), Tages-Streak mit Flamme, 5 Badges (Erster Blueprint, Bug-Jäger, 3-Tage-Streak, Erst-Versuch-Profi, Pfad-Meister). Persistenz lokal (Gast/Offline), optional Cloud-Sync.

Simulator-Bausteine (~23 Node-Typen): Events, Branch/Sequence/Gate, **ForLoop**, **DoOnce**, Variablen & Literale, Compare, Add/**Sub**/**Mul**/**Clamp**, **AND/OR**/NOT sowie sichtbare Aktionen (Tür, Licht, Print, SetDead).

Alle 32 Missionen sind testabgesichert: Jeder Bau-/Debug-Startgraph schlägt zunächst fehl, und für jede existiert eine Referenzlösung, die den Grader besteht (92 Tests gesamt).

Mobile: Simulator mit Pinch-Zoom und aufklappbarer Node-Palette (Bottom-Sheet), Bottom-Nav (Lernen · Täglich · Profil).

## Cloud-Sync aktivieren (optional)

Ohne Konfiguration läuft alles lokal (Gast-Modus). Für Konten & geräteübergreifenden Sync:

1. Kostenloses Projekt auf [supabase.com](https://supabase.com) anlegen.
2. `supabase/schema.sql` im **SQL-Editor** des Projekts ausführen (Tabelle `profiles` + RLS).
3. Unter **Authentication → Providers** den **Email**-Provider (Magic Link) aktivieren.
4. `.env.local.example` zu `.env.local` kopieren und `NEXT_PUBLIC_SUPABASE_URL` + `NEXT_PUBLIC_SUPABASE_ANON_KEY` (Project Settings → API) eintragen.
5. `npm run dev` — auf `/profile` per E-Mail anmelden. Der bisherige Gast-Fortschritt wird beim ersten Login **verlustfrei mit der Cloud verschmolzen**.

**Nächste Schritte:** Sandbox-Editor (Speichern/Teilen) · Skill-Tree-Visualisierung · weitere Pfade (Intermediate-Themen).

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
