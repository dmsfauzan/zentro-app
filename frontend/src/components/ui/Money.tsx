import { formatRupiah } from "@/lib/format";
import { cn } from "@/lib/cn";

export function Money({
  value,
  className,
}: {
  value: number;
  className?: string;
}) {
  return <span className={cn("num", className)}>{formatRupiah(value)}</span>;
}
