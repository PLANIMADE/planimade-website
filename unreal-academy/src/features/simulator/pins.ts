import type { PinType } from "@/engine";

/** Tailwind-Hintergrundklasse je Pin-Typ (nutzt die Design-Tokens). */
export const pinColor: Record<PinType, string> = {
  exec: "bg-pin-exec",
  bool: "bg-pin-bool",
  int: "bg-pin-int",
  float: "bg-pin-float",
  string: "bg-pin-string",
  object: "bg-pin-object",
};

/** Farbe für den Node-Kopf je Kategorie/Art. */
export const kindAccent: Record<string, string> = {
  event: "from-pin-bool/30 to-transparent",
  flow: "from-accent/25 to-transparent",
  pure: "from-pin-float/20 to-transparent",
  variable: "from-pin-int/20 to-transparent",
  action: "from-accent-2/25 to-transparent",
};
