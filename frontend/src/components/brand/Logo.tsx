import Image from "next/image";

interface LogoProps {
  className?: string;
  priority?: boolean;
  /** Tinggi tampilan logo (Tailwind class), default h-12. */
  heightClass?: string;
}

/**
 * Logo lockup resmi ZENTRO (mark + wordmark + tagline).
 * Aset sumber: public/brand/zentro-logo.jpg
 *
 * Komponen presentational: JANGAN menambahkan <Link> di sini — wrapper
 * (header/layout) yang menyediakan tautan agar tidak terjadi anchor bersarang.
 */
export function Logo({
  className = "",
  priority = false,
  heightClass = "h-12",
}: LogoProps) {
  return (
    <span className={`inline-flex w-fit items-center ${className}`}>
      <Image
        src="/brand/zentro-logo.png"
        alt="ZENTRO — Everything in One Place"
        width={985}
        height={300}
        priority={priority}
        className={`${heightClass} w-auto`}
      />
    </span>
  );
}

/**
 * Mark ZENTRO saja (untuk header ringkas / favicon-like usage).
 */
export function LogoMark({
  size = 40,
  className = "",
}: {
  size?: number;
  className?: string;
}) {
  return (
    <Image
      src="/brand/zentro-mark.png"
      alt="ZENTRO"
      width={size}
      height={size}
      className={className}
    />
  );
}
