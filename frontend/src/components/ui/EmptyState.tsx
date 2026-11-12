import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import { Icon } from "@/components/ui/Icon";

export function EmptyState({
  icon,
  title,
  description,
  action,
}: {
  icon?: LucideIcon;
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-[8px] border border-dashed border-line-strong px-6 py-14 text-center">
      {icon ? (
        <span className="flex size-10 items-center justify-center rounded-full bg-paper text-ink-subtle">
          <Icon as={icon} size="lg" />
        </span>
      ) : null}
      <div className="space-y-1">
        <p className="text-base font-medium text-ink">{title}</p>
        {description ? (
          <p className="mx-auto max-w-md text-sm text-ink-muted">{description}</p>
        ) : null}
      </div>
      {action}
    </div>
  );
}
