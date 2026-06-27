import { describe, expect, it } from "vitest";
import { allMissions } from "@/data/missions";
import { DAILY_BONUS, dailyPool, selectDailyMission } from "@/features/daily/daily";

describe("Daily Challenge", () => {
  it("ist deterministisch (gleicher Tag → gleiche Mission)", () => {
    const a = selectDailyMission("2026-06-27", allMissions);
    const b = selectDailyMission("2026-06-27", allMissions);
    expect(a?.id).toBe(b?.id);
  });

  it("wählt nur Bau-/Debug-Missionen", () => {
    const pool = new Set(dailyPool(allMissions).map((m) => m.id));
    for (let d = 1; d <= 60; d++) {
      const day = `2026-06-${String(d).padStart(2, "0")}`;
      const m = selectDailyMission(day, allMissions);
      expect(m).toBeDefined();
      expect(pool.has(m!.id)).toBe(true);
    }
  });

  it("rotiert über mehrere Tage (nicht immer dieselbe)", () => {
    const ids = new Set(
      Array.from({ length: 30 }, (_, i) =>
        selectDailyMission(`2026-07-${String(i + 1).padStart(2, "0")}`, allMissions)?.id,
      ),
    );
    expect(ids.size).toBeGreaterThan(1);
  });

  it("Bonus ist +50 %", () => {
    expect(DAILY_BONUS).toBe(0.5);
  });
});
