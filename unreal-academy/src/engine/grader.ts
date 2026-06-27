import { actionKinds, runGraph } from "./runtime";
import type { BPGraph, BPValue } from "./types";

/**
 * Verhaltensbasierter Grader.
 *
 * Statt den Graphen mit einer Musterlösung zu vergleichen (was nur EINEN Weg
 * erlauben würde), führen wir ihn gegen Testfälle aus und prüfen das *Verhalten*.
 * So werden alle korrekten Lösungswege akzeptiert – wie in echtem Gameplay-Code.
 */

export interface GraderCase {
  name?: string;
  /** Startwerte für Variablen vor dem Lauf. */
  given?: Record<string, BPValue>;
  /** Welches Event auslösen (Node-type/id). */
  trigger?: string;
  expect: {
    /** Exakte, geordnete Folge der ausgelösten Aktions-Kinds. */
    actions?: string[];
    /** Erwartete Endwerte einzelner Variablen. */
    variables?: Record<string, BPValue>;
  };
}

export interface GraderSpec {
  cases: GraderCase[];
}

export interface GraderCaseResult {
  name: string;
  passed: boolean;
  /** Menschlich lesbare Begründung bei Fehlschlag. */
  detail?: string;
}

export interface GraderResult {
  passed: boolean;
  cases: GraderCaseResult[];
}

function sameSequence(a: string[], b: string[]): boolean {
  return a.length === b.length && a.every((x, i) => x === b[i]);
}

/** Bewertet einen Nutzer-Graphen gegen die Testfälle einer Mission. */
export function gradeGraph(graph: BPGraph, spec: GraderSpec): GraderResult {
  const cases: GraderCaseResult[] = spec.cases.map((c, i) => {
    const name = c.name ?? `Fall ${i + 1}`;
    const result = runGraph(graph, {
      variables: c.given,
      trigger: c.trigger,
    });

    if (result.errors.length > 0) {
      return { name, passed: false, detail: result.errors[0].message };
    }

    if (c.expect.actions) {
      const got = actionKinds(result);
      if (!sameSequence(got, c.expect.actions)) {
        return {
          name,
          passed: false,
          detail: `Erwartete Aktionen [${c.expect.actions.join(", ")}], ausgelöst wurde [${got.join(", ")}].`,
        };
      }
    }

    if (c.expect.variables) {
      for (const [key, want] of Object.entries(c.expect.variables)) {
        const got = result.variables[key];
        if (got !== want) {
          return {
            name,
            passed: false,
            detail: `Variable "${key}": erwartet ${JSON.stringify(want)}, war ${JSON.stringify(got)}.`,
          };
        }
      }
    }

    return { name, passed: true };
  });

  return { passed: cases.every((c) => c.passed), cases };
}
