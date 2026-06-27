import { describe, expect, it } from "vitest";
import { allMissions, tracks } from "@/data/missions";
import type { Mission } from "@/features/missions/types";
import {
  advanceStreak,
  computeRank,
  evaluateBadges,
  isStreakAlive,
  type BadgeContext,
  type Streak,
} from "@/features/progression/progression";

const missionsById: Record<string, Mission> = Object.fromEntries(
  allMissions.map((m) => [m.id, m]),
);

function ctx(over: Partial<BadgeContext>): BadgeContext {
  return {
    xp: 0,
    completed: {},
    firstTry: [],
    streak: { count: 0, lastDay: null },
    missionsById,
    tracks,
    ...over,
  };
}

describe("computeRank", () => {
  it("ordnet XP dem richtigen Rang zu", () => {
    expect(computeRank(0).rank.title).toBe("Blueprint Anfänger");
    expect(computeRank(149).index).toBe(0);
    expect(computeRank(150).rank.title).toBe("Logic Builder");
    expect(computeRank(1500).rank.title).toBe("Unreal Wizard");
  });

  it("rechnet Fortschritt bis zum nächsten Rang", () => {
    const r = computeRank(275); // Mitte zwischen 150 und 400
    expect(r.next?.title).toBe("Gameplay Designer");
    expect(r.toNext).toBe(125);
    expect(r.progress).toBeCloseTo(0.5, 1);
  });

  it("Maximalrang hat keinen Nachfolger", () => {
    const r = computeRank(99999);
    expect(r.next).toBeNull();
    expect(r.toNext).toBe(0);
    expect(r.progress).toBe(1);
  });
});

describe("advanceStreak", () => {
  const base: Streak = { count: 3, lastDay: "2026-06-20" };

  it("erster Tag startet bei 1", () => {
    expect(advanceStreak({ count: 0, lastDay: null }, "2026-06-27")).toEqual({
      count: 1,
      lastDay: "2026-06-27",
    });
  });

  it("selber Tag bleibt unverändert", () => {
    expect(advanceStreak(base, "2026-06-20")).toEqual({
      count: 3,
      lastDay: "2026-06-20",
    });
  });

  it("Folgetag erhöht um 1", () => {
    expect(advanceStreak(base, "2026-06-21")).toEqual({
      count: 4,
      lastDay: "2026-06-21",
    });
  });

  it("Lücke setzt zurück auf 1", () => {
    expect(advanceStreak(base, "2026-06-25")).toEqual({
      count: 1,
      lastDay: "2026-06-25",
    });
  });
});

describe("isStreakAlive", () => {
  it("heute und gestern gelten als lebendig, ältere nicht", () => {
    expect(isStreakAlive({ count: 2, lastDay: "2026-06-27" }, "2026-06-27")).toBe(true);
    expect(isStreakAlive({ count: 2, lastDay: "2026-06-26" }, "2026-06-27")).toBe(true);
    expect(isStreakAlive({ count: 2, lastDay: "2026-06-24" }, "2026-06-27")).toBe(false);
    expect(isStreakAlive({ count: 0, lastDay: null }, "2026-06-27")).toBe(false);
  });
});

describe("evaluateBadges", () => {
  it("Erster Blueprint nach einer Bau-Mission", () => {
    expect(evaluateBadges(ctx({ completed: { A2: 3 } }))).toContain("first-blueprint");
  });

  it("Bug-Jäger nach einer Debug-Mission", () => {
    expect(evaluateBadges(ctx({ completed: { A7: 2 } }))).toContain("bug-hunter");
  });

  it("3-Tage-Streak ab count 3", () => {
    expect(evaluateBadges(ctx({ streak: { count: 3, lastDay: "2026-06-27" } }))).toContain(
      "streak-3",
    );
  });

  it("Erst-Versuch-Profi ab 5 Erst-Versuchen", () => {
    expect(
      evaluateBadges(ctx({ firstTry: ["A1", "A2", "A3", "A4", "A5"] })),
    ).toContain("first-try-pro");
  });

  it("Pfad-Meister erst bei komplettem Pfad", () => {
    const partial = ctx({ completed: { A1: 1, A2: 1 } });
    expect(evaluateBadges(partial)).not.toContain("path-master");

    const full = Object.fromEntries(tracks[0].missions.map((m) => [m.id, 3]));
    expect(evaluateBadges(ctx({ completed: full }))).toContain("path-master");
  });

  it("ohne Fortschritt keine Badges", () => {
    expect(evaluateBadges(ctx({}))).toEqual([]);
  });
});
