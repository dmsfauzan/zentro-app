"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";

function createClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        retry: 1,
      },
    },
  });
}

let browserClient: QueryClient | undefined;

export function QueryProvider({ children }: { children: ReactNode }) {
  const [client] = useState(() => {
    if (typeof window === "undefined") return createClient();

    browserClient ??= createClient();

    return browserClient;
  });

  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
