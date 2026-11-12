import type { ReactNode } from "react";
import { cn } from "@/lib/cn";
import { statusMeta, type StatusDomain } from "@/lib/design/status";
import { toneSurface } from "@/lib/design/tone";

export function Badge({
  children,
  tone = "neutral",
  className,
}: {
  children: ReactNode;
  tone?: "neutral" | "success" | "warning" | "danger" | "info" | "brand";
  className?: string;
}) {
  const palette =
    tone === "brand" ? "bg-brand-soft text-brand" : toneSurface[tone];

  return (
    <span
      className={cn(
        "inline-flex h-5 items-center rounded-[6px] px-2 text-xs font-medium",
        palette,
        className,
      )}
    >
      {children}
    </span>
  );
}

export function StatusBadge({
  domain,
  value,
  className,
}: {
  domain: StatusDomain;
  value: string | null | undefined;
  className?: string;
}) {
  const meta = statusMeta(domain, value);
  const palette = toneSurface[meta.tone];

  return (
    <span
      className={cn(
        "inline-flex h-5 items-center gap-1.5 rounded-[6px] px-2 text-xs font-medium",
        palette,
        className,
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          "size-1.5 rounded-full",
          meta.tone === "success" && "bg-success",
          meta.tone === "warning" && "bg-warning",
          meta.tone === "danger" && "bg-danger",
          meta.tone === "info" && "bg-info",
          meta.tone === "neutral" && "bg-ink-subtle",
        )}
      />
      {meta.label}
    </span>
  );
}
