"use client";

import * as TabsPrimitive from "@radix-ui/react-tabs";
import type { ReactNode } from "react";
import { cn } from "@/lib/cn";

export function Tabs({ children, defaultValue }: { children: ReactNode; defaultValue: string }) {
  return (
    <TabsPrimitive.Root defaultValue={defaultValue}>{children}</TabsPrimitive.Root>
  );
}

export function TabsList({ children }: { children: ReactNode }) {
  return (
    <TabsPrimitive.List className="flex gap-6 border-b border-line">
      {children}
    </TabsPrimitive.List>
  );
}

export function TabsTrigger({ value, children }: { value: string; children: ReactNode }) {
  return (
    <TabsPrimitive.Trigger
      value={value}
      className={cn(
        "-mb-px border-b-2 border-transparent pb-2.5 text-sm font-medium text-ink-muted transition-colors",
        "data-[state=active]:border-brand data-[state=active]:text-ink",
      )}
    >
      {children}
    </TabsPrimitive.Trigger>
  );
}

export function TabsContent({ value, children }: { value: string; children: ReactNode }) {
  return (
    <TabsPrimitive.Content value={value} className="pt-4 focus:outline-none">
      {children}
    </TabsPrimitive.Content>
  );
}
