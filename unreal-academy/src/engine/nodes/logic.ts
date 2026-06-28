import { defineNode } from "../registry";

/** Logik & Mathe (pure Nodes). */

export const compare = defineNode({
  type: "math.compare",
  title: "Compare",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "a", type: "float", label: "A", default: 0 },
    { id: "b", type: "float", label: "B", default: 0 },
  ],
  dataOut: [{ id: "result", type: "bool", label: "Result" }],
  pure: (ctx) => {
    const a = Number(ctx.input("a"));
    const b = Number(ctx.input("b"));
    const op = String(ctx.node.data?.op ?? "==");
    let result = false;
    switch (op) {
      case "==":
        result = a === b;
        break;
      case "!=":
        result = a !== b;
        break;
      case ">":
        result = a > b;
        break;
      case "<":
        result = a < b;
        break;
      case ">=":
        result = a >= b;
        break;
      case "<=":
        result = a <= b;
        break;
    }
    return { result };
  },
});

export const add = defineNode({
  type: "math.add",
  title: "Add (int)",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "a", type: "int", label: "A", default: 0 },
    { id: "b", type: "int", label: "B", default: 0 },
  ],
  dataOut: [{ id: "sum", type: "int", label: "Sum" }],
  pure: (ctx) => ({ sum: Number(ctx.input("a")) + Number(ctx.input("b")) }),
});

export const not = defineNode({
  type: "logic.not",
  title: "NOT",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [{ id: "in", type: "bool", label: "In", default: false }],
  dataOut: [{ id: "out", type: "bool", label: "Out" }],
  pure: (ctx) => ({ out: !ctx.input("in") }),
});

export const and = defineNode({
  type: "logic.and",
  title: "AND",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "a", type: "bool", label: "A", default: false },
    { id: "b", type: "bool", label: "B", default: false },
  ],
  dataOut: [{ id: "out", type: "bool", label: "Out" }],
  pure: (ctx) => ({ out: Boolean(ctx.input("a")) && Boolean(ctx.input("b")) }),
});

export const or = defineNode({
  type: "logic.or",
  title: "OR",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "a", type: "bool", label: "A", default: false },
    { id: "b", type: "bool", label: "B", default: false },
  ],
  dataOut: [{ id: "out", type: "bool", label: "Out" }],
  pure: (ctx) => ({ out: Boolean(ctx.input("a")) || Boolean(ctx.input("b")) }),
});

export const subtract = defineNode({
  type: "math.sub",
  title: "Subtract (int)",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "a", type: "int", label: "A", default: 0 },
    { id: "b", type: "int", label: "B", default: 0 },
  ],
  dataOut: [{ id: "diff", type: "int", label: "Diff" }],
  pure: (ctx) => ({ diff: Number(ctx.input("a")) - Number(ctx.input("b")) }),
});

export const multiply = defineNode({
  type: "math.mul",
  title: "Multiply (int)",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "a", type: "int", label: "A", default: 0 },
    { id: "b", type: "int", label: "B", default: 0 },
  ],
  dataOut: [{ id: "product", type: "int", label: "Product" }],
  pure: (ctx) => ({ product: Number(ctx.input("a")) * Number(ctx.input("b")) }),
});

/** Clamp: hält einen Wert zwischen Min und Max (z.B. Health nie unter 0). */
export const clamp = defineNode({
  type: "math.clamp",
  title: "Clamp (int)",
  category: "Logic",
  kind: "pure",
  execIn: [],
  execOut: [],
  dataIn: [
    { id: "value", type: "int", label: "Value", default: 0 },
    { id: "min", type: "int", label: "Min", default: 0 },
    { id: "max", type: "int", label: "Max", default: 100 },
  ],
  dataOut: [{ id: "result", type: "int", label: "Result" }],
  pure: (ctx) => {
    const v = Number(ctx.input("value"));
    const lo = Number(ctx.input("min"));
    const hi = Number(ctx.input("max"));
    return { result: Math.max(lo, Math.min(hi, v)) };
  },
});
