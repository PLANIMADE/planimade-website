import { defineNode } from "../registry";

/**
 * Sichtbare Aktionen. Jede löst über ctx.emit einen Seiteneffekt aus, der
 * auf der Simulator-„Bühne" sichtbar wird und vom Grader geprüft werden kann.
 */

export const openDoor = defineNode({
  type: "action.openDoor",
  title: "Open Door",
  category: "Actions",
  kind: "action",
  execIn: ["in"],
  execOut: ["then"],
  dataIn: [],
  dataOut: [],
  exec: (ctx) => {
    ctx.emit({ kind: "OpenDoor" });
    return "then";
  },
});

export const toggleLight = defineNode({
  type: "action.toggleLight",
  title: "Toggle Light",
  category: "Actions",
  kind: "action",
  execIn: ["in"],
  execOut: ["then"],
  dataIn: [],
  dataOut: [],
  exec: (ctx) => {
    ctx.emit({ kind: "ToggleLight" });
    return "then";
  },
});

export const printString = defineNode({
  type: "action.print",
  title: "Print String",
  category: "Actions",
  kind: "action",
  execIn: ["in"],
  execOut: ["then"],
  dataIn: [{ id: "text", type: "string", label: "Text", default: "Hello" }],
  dataOut: [],
  exec: (ctx) => {
    ctx.emit({ kind: "Print", payload: { text: String(ctx.input("text")) } });
    return "then";
  },
});

export const setDead = defineNode({
  type: "action.setDead",
  title: "Set Dead",
  category: "Actions",
  kind: "action",
  execIn: ["in"],
  execOut: ["then"],
  dataIn: [],
  dataOut: [],
  exec: (ctx) => {
    ctx.emit({ kind: "SetDead" });
    return "then";
  },
});
