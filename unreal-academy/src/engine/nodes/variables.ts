import { defineNode } from "../registry";
import type { BPValue, PinType } from "../types";

/**
 * Variablen & Literale.
 * Get-/Set-Node referenzieren `node.data.varName`.
 * Literale liefern ihren `node.data.value`.
 */

export const getVariable = defineNode({
  type: "var.get",
  title: "Get Variable",
  category: "Variables",
  kind: "variable",
  execIn: [],
  execOut: [],
  // Der Ausgangstyp ist generisch; der Grader vergleicht Werte, nicht Typen.
  dataIn: [],
  dataOut: [{ id: "value", type: "object", label: "Value" }],
  pure: (ctx) => {
    const name = String(ctx.node.data?.varName ?? "");
    return { value: ctx.getVar(name) };
  },
});

export const setVariable = defineNode({
  type: "var.set",
  title: "Set Variable",
  category: "Variables",
  kind: "action",
  execIn: ["in"],
  execOut: ["then"],
  dataIn: [{ id: "value", type: "object", label: "Value" }],
  dataOut: [{ id: "out", type: "object", label: "Value" }],
  exec: (ctx) => {
    const name = String(ctx.node.data?.varName ?? "");
    ctx.setVar(name, ctx.input("value"));
    return "then";
  },
  // Erlaubt Verkettung: liest den aktuellen Wert nach dem Setzen.
  pure: (ctx) => {
    const name = String(ctx.node.data?.varName ?? "");
    return { out: ctx.getVar(name) };
  },
});

function literal(
  type: string,
  title: string,
  pinType: Exclude<PinType, "exec">,
  coerce: (v: unknown) => BPValue,
) {
  return defineNode({
    type,
    title,
    category: "Variables",
    kind: "pure",
    execIn: [],
    execOut: [],
    dataIn: [],
    dataOut: [{ id: "value", type: pinType, label: "Value" }],
    pure: (ctx) => ({ value: coerce(ctx.node.data?.value) }),
  });
}

export const literalBool = literal("literal.bool", "Bool", "bool", (v) => !!v);
export const literalInt = literal("literal.int", "Integer", "int", (v) => {
  const n = Number(v);
  return Number.isFinite(n) ? Math.trunc(n) : 0;
});
