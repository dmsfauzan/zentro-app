"use client";

import { Minus, Plus } from "lucide-react";
import { Icon } from "@/components/ui/Icon";

export function QuantityStepper({
  value,
  onChange,
  min = 1,
  max = 999,
  disabled,
}: {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  disabled?: boolean;
}) {
  const clamp = (next: number) => Math.max(min, Math.min(max, next));

  return (
    <div className="inline-flex h-9 items-center rounded-[8px] border border-line-strong bg-canvas">
      <button
        type="button"
        onClick={() => onChange(clamp(value - 1))}
        disabled={disabled || value <= min}
        className="inline-flex size-9 items-center justify-center text-ink-muted disabled:opacity-40"
        aria-label="Kurangi jumlah"
      >
        <Icon as={Minus} size="xs" />
      </button>
      <span className="num w-9 text-center text-sm font-medium text-ink" aria-live="polite">
        {value}
      </span>
      <button
        type="button"
        onClick={() => onChange(clamp(value + 1))}
        disabled={disabled || value >= max}
        className="inline-flex size-9 items-center justify-center text-ink-muted disabled:opacity-40"
        aria-label="Tambah jumlah"
      >
        <Icon as={Plus} size="xs" />
      </button>
    </div>
  );
}
