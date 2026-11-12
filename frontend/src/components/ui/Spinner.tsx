import { Loader2 } from "lucide-react";
import { cn } from "@/lib/cn";

export function Spinner({ className }: { className?: string }) {
  return (
    <Loader2
      size={16}
      strokeWidth={1.5}
      className={cn("animate-spin text-ink-muted", className)}
      aria-hidden="true"
    />
  );
}

export function PageLoader({ label = "Memuat…" }: { label?: string }) {
  return (
    <div className="flex items-center justify-center gap-2 py-16 text-sm text-ink-muted">
      <Spinner />
      {label}
    </div>
  );
}
