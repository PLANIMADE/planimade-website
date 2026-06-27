import { defineNode } from "../registry";

/** Events sind Einstiegspunkte: kein Exec-Eingang, ein Exec-Ausgang "then". */

export const eventBeginPlay = defineNode({
  type: "event.beginPlay",
  title: "Event BeginPlay",
  category: "Events",
  kind: "event",
  execIn: [],
  execOut: ["then"],
  dataIn: [],
  dataOut: [],
  exec: () => "then",
});

export const eventActionPressed = defineNode({
  type: "event.actionPressed",
  title: "Action Pressed",
  category: "Events",
  kind: "event",
  execIn: [],
  execOut: ["then"],
  dataIn: [],
  dataOut: [],
  exec: () => "then",
});
