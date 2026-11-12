import type { ReactNode } from "react";
import { AlertTriangle, CircleCheck, Info, CircleX } from "lucide-react";
import { cn } from "@/lib/cn";
import type { Tone } from "@/lib/design/tone";
import { Icon } from "@/components/ui/Icon";

const config: Record<
  Tone,
  { icon: typeof Info; className: string }
> = {
  info: { icon: Info, className: "border-info/30 bg-info-soft text-info" },
  success: {
    icon: CircleCheck,
    className: "border-success/30 bg-success-soft text-success",
  },
  warning: {
    icon: AlertTriangle,
    className: "border-warning/30 bg-warning-soft text-warning",
  },
  danger: {
    icon: CircleX,
    className: "border-danger/30 bg-danger-soft text-danger",
  },
  neutral: { icon: Info, className: "border-line bg-paper text-ink-muted" },
};

export function Alert({
  tone = "info",
  title,
  children,
  className,
}: {
  tone?: Tone;
  title?: string;
  children?: ReactNode;
  className?: string;
}) {
  const { icon, className: palette } = config[tone];

  return (
    <div
      className={cn("flex gap-3 rounded-[8px] border px-3.5 py-3", palette, className)}
      role="status"
    >
      <Icon as={icon} size="sm" className="mt-0.5 shrink-0" />
      <div className="min-w-0 text-sm">
        {title ? <p className="font-medium">{title}</p> : null}
        {children ? <div className="text-ink-muted">{children}</div> : null}
      </div>
    </div>
  );
}
