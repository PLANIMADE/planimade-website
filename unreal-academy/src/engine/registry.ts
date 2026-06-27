import type { NodeSpec, PinType } from "./types";

/**
 * Zentrale Node-Registry. Node-Typen werden deklarativ registriert und vom
 * Runtime, Validator und der UI (Palette) gemeinsam genutzt — eine Quelle.
 */
const registry = new Map<string, NodeSpec>();

/** Registriert einen Node-Typ. Doppelte Typen werden überschrieben (Dev-Hot-Reload). */
export function defineNode(spec: NodeSpec): NodeSpec {
  registry.set(spec.type, spec);
  return spec;
}

export function getNodeSpec(type: string): NodeSpec | undefined {
  return registry.get(type);
}

export function allNodeSpecs(): NodeSpec[] {
  return [...registry.values()];
}

/** Default-Wert für einen Pin-Typ (wenn unverbunden & ohne expliziten Default). */
export function zeroValue(type: PinType): boolean | number | string | null {
  switch (type) {
    case "bool":
      return false;
    case "int":
    case "float":
      return 0;
    case "string":
      return "";
    default:
      return null;
  }
}
