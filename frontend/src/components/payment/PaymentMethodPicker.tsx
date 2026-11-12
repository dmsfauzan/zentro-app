"use client";

import { useMemo, useState } from "react";
import type { PaymentMethodOption } from "@/lib/api/domain";
import { PaymentMethodLogo } from "@/components/payment/PaymentMethodLogo";
import { cn } from "@/lib/cn";

const TABS = [
  { key: "va", label: "Transfer Bank", groups: ["Virtual Account"] },
  { key: "wallet", label: "e-Wallet / QRIS", groups: ["QRIS", "E-wallet"] },
] as const;

const COMING_SOON = [
  { key: "card", label: "Kartu Kredit/Debit" },
  { key: "cash", label: "Gerai Tunai" },
] as const;

type Props = {
  methods: PaymentMethodOption[];
  value: string | null;
  onChange: (key: string) => void;
  disabled?: boolean;
};

/**
 * Pemilih metode pembayaran dengan tab (dipakai di checkout & halaman pembayaran).
 */
export function PaymentMethodPicker({ methods, value, onChange, disabled = false }: Props) {
  const tabOf = (method: PaymentMethodOption) =>
    TABS.find((tab) => (tab.groups as readonly string[]).includes(method.group))?.key ?? "va";

  const [tab, setTab] = useState<string>(() => {
    const selected = methods.find((method) => method.key === value);

    return selected ? tabOf(selected) : "va";
  });

  const visible = useMemo(
    () => (methods.length === 0 ? [] : methods.filter((method) => tabOf(method) === tab)),
    [methods, tab],
  );

  if (methods.length === 0) {
    return (
      <p className="text-sm text-ink-muted">
        Metode pembayaran belum tersedia. Pilih metode di halaman pembayaran setelah pesanan dibuat.
      </p>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end gap-x-6 gap-y-2 border-b border-line">
        {TABS.map((item) => {
          const active = tab === item.key;

          return (
            <button
              key={item.key}
              type="button"
              onClick={() => setTab(item.key)}
              aria-pressed={active}
              className={cn(
                "-mb-px border-b-2 pb-2 text-sm font-medium transition-colors",
                active
                  ? "border-brand text-ink"
                  : "border-transparent text-ink-muted hover:text-ink",
              )}
            >
              {item.label}
            </button>
          );
        })}
        {COMING_SOON.map((item) => (
          <span
            key={item.key}
            title="Belum tersedia"
            className="-mb-px flex items-center gap-1.5 border-b-2 border-transparent pb-2 text-sm font-medium text-ink-subtle/70"
          >
            {item.label}
            <span className="rounded-[4px] bg-canvas px-1.5 py-0.5 text-[10px] font-normal text-ink-subtle">
              Segera
            </span>
          </span>
        ))}
      </div>

      <div className="grid gap-2 sm:grid-cols-2">
        {visible.map((method) => {
          const active = method.key === value;

          return (
            <button
              key={method.key}
              type="button"
              disabled={disabled}
              aria-pressed={active}
              onClick={() => onChange(method.key)}
              className={cn(
                "flex items-center justify-between gap-3 rounded-[8px] border px-3 py-2.5 text-left transition",
                active ? "border-brand bg-brand-soft" : "border-line hover:border-line-strong",
                disabled && "opacity-60",
              )}
            >
              <span className="flex items-center gap-2 text-sm font-medium text-ink">
                <span
                  aria-hidden="true"
                  className={cn(
                    "flex size-4 shrink-0 items-center justify-center rounded-full border",
                    active ? "border-brand" : "border-line-strong",
                  )}
                >
                  {active ? <span className="size-2 rounded-full bg-brand" /> : null}
                </span>
                {method.label}
              </span>
              <PaymentMethodLogo method={method.key} label={method.label} />
            </button>
          );
        })}
      </div>
    </div>
  );
}
