import { describe, expect, it } from "vitest";
import { normalizeProgress, type LocalProgress } from "@/features/progression/local";
import { mergeProgress } from "@/features/progression/merge";

function mk(p: Partial<LocalProgress>): LocalProgress {
  return normalizeProgress(p);
}

describe("mergeProgress – verlustfrei & deterministisch", () => {
  it("nimmt die höhere XP und die höhere Sternezahl pro Mission", () => {
    const a = mk({ xp: 100, completed: { A1: 3, A2: 1 } });
    const b = mk({ xp: 80, completed: { A2: 2, A4: 3 } });
    const m = mergeProgress(a, b);
    expect(m.xp).toBe(100);
    expect(m.completed).toEqual({ A1: 3, A2: 2, A4: 3 });
  });

  it("vereinigt firstTry und badges ohne Duplikate", () => {
    const a = mk({ firstTry: ["A1", "A2"], badges: ["first-blueprint"] });
    const b = mk({ firstTry: ["A2", "A3"], badges: ["bug-hunter"] });
    const m = mergeProgress(a, b);
    expect(new Set(m.firstTry)).toEqual(new Set(["A1", "A2", "A3"]));
    expect(new Set(m.badges)).toEqual(new Set(["first-blueprint", "bug-hunter"]));
  });

  it("wählt die stärkere Streak (Gleichstand → späterer Tag)", () => {
    expect(
      mergeProgress(
        mk({ streak: { count: 5, lastDay: "2026-06-20" } }),
        mk({ streak: { count: 3, lastDay: "2026-06-27" } }),
      ).streak,
    ).toEqual({ count: 5, lastDay: "2026-06-20" });

    expect(
      mergeProgress(
        mk({ streak: { count: 3, lastDay: "2026-06-20" } }),
        mk({ streak: { count: 3, lastDay: "2026-06-27" } }),
      ).streak,
    ).toEqual({ count: 3, lastDay: "2026-06-27" });
  });

  it("nimmt den späteren Daily-Tag und ODER-verknüpft onboarded", () => {
    const m = mergeProgress(
      mk({ dailyDoneDay: "2026-06-25", onboarded: false, goal: null }),
      mk({ dailyDoneDay: "2026-06-27", onboarded: true, goal: "B" }),
    );
    expect(m.dailyDoneDay).toBe("2026-06-27");
    expect(m.onboarded).toBe(true);
    expect(m.goal).toBe("B");
  });

  it("ist kommutativ (Reihenfolge egal)", () => {
    const a = mk({
      xp: 120,
      completed: { A1: 3, B2: 2 },
      firstTry: ["A1"],
      badges: ["streak-3"],
      streak: { count: 4, lastDay: "2026-06-26" },
      dailyDoneDay: "2026-06-26",
      onboarded: true,
      goal: "A",
    });
    const b = mk({
      xp: 90,
      completed: { A1: 2, C3: 1 },
      firstTry: ["C3"],
      badges: ["bug-hunter"],
      streak: { count: 4, lastDay: "2026-06-27" },
      dailyDoneDay: "2026-06-27",
      onboarded: false,
      goal: "C",
    });
    expect(mergeProgress(a, b)).toEqual(mergeProgress(b, a));
  });
});
