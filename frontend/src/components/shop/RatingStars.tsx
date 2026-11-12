import { Star } from "lucide-react";
import { Icon } from "@/components/ui/Icon";
import { cn } from "@/lib/cn";

/** Bintang rating 1–5 (mengikuti gaya template: bintang terisi berwarna). */
export function RatingStars({
  value,
  size = "xs",
  className,
}: {
  value: number;
  size?: "xs" | "sm" | "md";
  className?: string;
}) {
  const rounded = Math.round(value);

  return (
    <span
      className={cn("inline-flex items-center gap-0.5", className)}
      aria-label={`${value.toFixed(1)} dari 5`}
    >
      {Array.from({ length: 5 }).map((_, index) => (
        <Icon
          key={index}
          as={Star}
          size={size}
          fill={index < rounded}
          className={index < rounded ? "text-rating" : "text-line-strong"}
        />
      ))}
    </span>
  );
}
