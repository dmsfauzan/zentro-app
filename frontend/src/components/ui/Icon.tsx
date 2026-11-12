import type { LucideIcon } from "lucide-react";

const sizes = { xs: 14, sm: 16, md: 18, lg: 20, xl: 24 } as const;

type IconProps = {
  as: LucideIcon;
  size?: keyof typeof sizes;
  className?: string;
  /** Isi bila ikon bermakna sendiri (ikon-only). */
  label?: string;
  /** Hanya untuk ikon rating (Star). */
  fill?: boolean;
};

/** Satu-satunya pintu masuk Lucide: stroke & ukuran konsisten, tanpa emoji. */
export function Icon({ as: Glyph, size = "sm", className, label, fill }: IconProps) {
  return (
    <Glyph
      size={sizes[size]}
      strokeWidth={1.5}
      className={className}
      fill={fill ? "currentColor" : "none"}
      aria-hidden={label ? undefined : true}
      aria-label={label}
      role={label ? "img" : undefined}
    />
  );
}
