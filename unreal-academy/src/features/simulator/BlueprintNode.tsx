"use client";

import { memo } from "react";
import { Handle, Position, useReactFlow, type NodeProps } from "reactflow";
import { getNodeSpec } from "@/engine";
import { cn } from "@/lib/cn";
import { kindAccent, pinColor } from "./pins";
import type { RFNodeData } from "./convert";

const HEADER = 34;
const ROW = 26;

interface PinRow {
  id: string;
  label: string;
  color: string;
  side: "left" | "right";
  handle: "source" | "target";
}

function BlueprintNodeImpl({ id, data, selected }: NodeProps<RFNodeData>) {
  const { setNodes } = useReactFlow();
  const spec = getNodeSpec(data.specType);
  if (!spec) {
    return (
      <div className="rounded-lg border border-danger bg-surface px-3 py-2 text-xs text-danger">
        Unbekannt: {data.specType}
      </div>
    );
  }

  const left: PinRow[] = [
    ...spec.execIn.map((pid) => ({
      id: pid,
      label: pid,
      color: pinColor.exec,
      side: "left" as const,
      handle: "target" as const,
    })),
    ...spec.dataIn.map((p) => ({
      id: p.id,
      label: p.label ?? p.id,
      color: pinColor[p.type],
      side: "left" as const,
      handle: "target" as const,
    })),
  ];
  const right: PinRow[] = [
    ...spec.execOut.map((pid) => ({
      id: pid,
      label: pid,
      color: pinColor.exec,
      side: "right" as const,
      handle: "source" as const,
    })),
    ...spec.dataOut.map((p) => ({
      id: p.id,
      label: p.label ?? p.id,
      color: pinColor[p.type],
      side: "right" as const,
      handle: "source" as const,
    })),
  ];

  const rows = Math.max(left.length, right.length, 1);
  const bodyHeight = rows * ROW;

  const setValue = (key: string, value: unknown) =>
    setNodes((nodes) =>
      nodes.map((n) =>
        n.id === id
          ? { ...n, data: { ...n.data, values: { ...n.data.values, [key]: value } } }
          : n,
      ),
    );

  return (
    <div
      className={cn(
        "w-48 select-none rounded-xl border bg-surface text-text shadow-node",
        selected ? "border-accent shadow-glow" : "border-border",
      )}
    >
      <div
        className={cn(
          "rounded-t-xl bg-gradient-to-b px-3 text-[13px] font-semibold leading-[34px]",
          kindAccent[spec.kind] ?? "from-accent/20 to-transparent",
        )}
      >
        {spec.title}
      </div>

      <div className="relative" style={{ height: bodyHeight }}>
        {/* Linke Pins */}
        {left.map((row, i) => (
          <PinLabel key={`l-${row.id}`} row={row} top={i * ROW} />
        ))}
        {/* Rechte Pins */}
        {right.map((row, i) => (
          <PinLabel key={`r-${row.id}`} row={row} top={i * ROW} />
        ))}
      </div>

      <NodeEditor specType={data.specType} values={data.values} onChange={setValue} />

      {/* Handles (absolut an den Zeilen ausgerichtet) */}
      {left.map((row, i) => (
        <Handle
          key={`hl-${row.id}`}
          id={row.id}
          type="target"
          position={Position.Left}
          style={{ top: HEADER + i * ROW + ROW / 2 }}
          className={cn("!h-3 !w-3 !border-2 !border-bg", row.color)}
        />
      ))}
      {right.map((row, i) => (
        <Handle
          key={`hr-${row.id}`}
          id={row.id}
          type="source"
          position={Position.Right}
          style={{ top: HEADER + i * ROW + ROW / 2 }}
          className={cn("!h-3 !w-3 !border-2 !border-bg", row.color)}
        />
      ))}
    </div>
  );
}

function PinLabel({ row, top }: { row: PinRow; top: number }) {
  return (
    <div
      className={cn(
        "absolute flex items-center gap-1.5 text-[11px] text-muted",
        row.side === "left" ? "left-2.5" : "right-2.5 flex-row-reverse",
      )}
      style={{ top, height: ROW }}
    >
      <span className={cn("h-2 w-2 rounded-full", row.color)} />
      <span>{row.label}</span>
    </div>
  );
}

/** Inline-Editoren für die paar Node-Typen mit konfigurierbaren Werten. */
function NodeEditor({
  specType,
  values,
  onChange,
}: {
  specType: string;
  values: Record<string, unknown>;
  onChange: (key: string, value: unknown) => void;
}) {
  if (specType === "literal.bool") {
    const v = !!values.value;
    return (
      <div className="border-t border-border p-2">
        <button
          onClick={() => onChange("value", !v)}
          className={cn(
            "w-full rounded-md py-1 text-xs font-semibold",
            v ? "bg-success/20 text-success" : "bg-danger/20 text-danger",
          )}
        >
          {v ? "true" : "false"}
        </button>
      </div>
    );
  }
  if (specType === "literal.int") {
    return (
      <div className="border-t border-border p-2">
        <input
          type="number"
          value={Number(values.value ?? 0)}
          onChange={(e) => onChange("value", Number(e.target.value))}
          className="w-full rounded-md border border-border bg-surface-2 px-2 py-1 text-xs"
        />
      </div>
    );
  }
  if (specType === "var.get" || specType === "var.set") {
    return (
      <div className="border-t border-border p-2">
        <input
          type="text"
          placeholder="Variablenname"
          value={String(values.varName ?? "")}
          onChange={(e) => onChange("varName", e.target.value)}
          className="w-full rounded-md border border-border bg-surface-2 px-2 py-1 text-xs"
        />
      </div>
    );
  }
  if (specType === "math.compare") {
    return (
      <div className="border-t border-border p-2">
        <select
          value={String(values.op ?? "==")}
          onChange={(e) => onChange("op", e.target.value)}
          className="w-full rounded-md border border-border bg-surface-2 px-2 py-1 text-xs"
        >
          {["==", "!=", ">", "<", ">=", "<="].map((op) => (
            <option key={op} value={op}>
              {op}
            </option>
          ))}
        </select>
      </div>
    );
  }
  return null;
}

export const BlueprintNode = memo(BlueprintNodeImpl);
