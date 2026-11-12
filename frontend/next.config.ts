import type { NextConfig } from "next";

const isDev = process.env.NODE_ENV !== "production";

/** Origin backend untuk `connect-src` CSP (dinamis dari env). */
function apiOrigin(): string {
  const base =
    process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api/v1";

  try {
    return new URL(base).origin;
  } catch {
    return "http://localhost:8000";
  }
}

const connectSources = [
  "'self'",
  apiOrigin(),
  // Firebase Auth & Firestore/Storage endpoints.
  "https://*.googleapis.com",
  "https://*.firebaseapp.com",
  "https://*.firebaseio.com",
  "wss://*.firebaseio.com",
  ...(isDev ? ["ws:", "http://localhost:8000", "http://127.0.0.1:8000"] : []),
];

// Gambar produk disajikan backend (mis. http://localhost:8000/uploads/products/...).
const imageSources = [
  "'self'",
  "blob:",
  "data:",
  "https:",
  apiOrigin(),
  ...(isDev ? ["http://localhost:8000", "http://127.0.0.1:8000"] : []),
];

const csp = [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline'${isDev ? " 'unsafe-eval'" : ""} https://apis.google.com https://www.gstatic.com`,
  "style-src 'self' 'unsafe-inline'",
  `img-src ${imageSources.join(" ")}`,
  "font-src 'self' data:",
  `connect-src ${connectSources.join(" ")}`,
  "frame-src 'self' https://*.firebaseapp.com https://accounts.google.com",
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
  isDev ? "" : "upgrade-insecure-requests",
]
  .filter(Boolean)
  .join("; ");

const securityHeaders = [
  { key: "Content-Security-Policy", value: csp },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  {
    key: "Permissions-Policy",
    value: "camera=(), microphone=(), geolocation=()",
  },
  { key: "X-DNS-Prefetch-Control", value: "on" },
];

const nextConfig: NextConfig = {
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  async headers() {
    return [
      {
        source: "/(.*)",
        headers: securityHeaders,
      },
    ];
  },
};

export default nextConfig;
