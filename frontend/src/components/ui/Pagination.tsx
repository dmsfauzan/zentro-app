"use client";

import { ChevronLeft, ChevronRight } from "lucide-react";
import { Icon } from "@/components/ui/Icon";
import { cn } from "@/lib/cn";

export function Pagination({
  page,
  lastPage,
  onChange,
  className,
}: {
  page: number;
  lastPage: number;
  onChange: (page: number) => void;
  className?: string;
}) {
  if (lastPage <= 1) return null;

  return (
    <nav
      className={cn("flex items-center justify-between gap-3 py-4", className)}
      aria-label="Navigasi halaman"
    >
      <button
        type="button"
        onClick={() => onChange(page - 1)}
        disabled={page <= 1}
        className="inline-flex h-8 items-center gap-1 rounded-[8px] border border-line-strong bg-canvas px-2.5 text-[13px] text-ink disabled:opacity-40"
      >
        <Icon as={ChevronLeft} size="xs" />
        Sebelumnya
      </button>

      <span className="num text-[13px] text-ink-muted">
        {page} / {lastPage}
      </span>

      <button
        type="button"
        onClick={() => onChange(page + 1)}
        disabled={page >= lastPage}
        className="inline-flex h-8 items-center gap-1 rounded-[8px] border border-line-strong bg-canvas px-2.5 text-[13px] text-ink disabled:opacity-40"
      >
        Berikutnya
        <Icon as={ChevronRight} size="xs" />
      </button>
    </nav>
  );
}
