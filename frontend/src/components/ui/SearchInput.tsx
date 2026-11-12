"use client";

import { Search } from "lucide-react";
import type { FormEvent } from "react";
import { Icon } from "@/components/ui/Icon";
import { cn } from "@/lib/cn";

export function SearchInput({
  defaultValue = "",
  placeholder = "Cari produk…",
  onSubmit,
  className,
}: {
  defaultValue?: string;
  placeholder?: string;
  onSubmit: (value: string) => void;
  className?: string;
}) {
  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const value = new FormData(event.currentTarget).get("q");
    onSubmit(String(value ?? "").trim());
  };

  return (
    <form onSubmit={submit} className={cn("relative", className)} role="search">
      <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-subtle">
        <Icon as={Search} size="sm" />
      </span>
      <input
        name="q"
        defaultValue={defaultValue}
        placeholder={placeholder}
        aria-label="Cari produk"
        className="h-11 w-full rounded-full border border-transparent bg-paper pl-10 pr-4 text-sm text-ink placeholder:text-ink-subtle focus:border-brand focus:bg-canvas focus:outline-none focus:ring-2 focus:ring-brand-soft"
      />
    </form>
  );
}
