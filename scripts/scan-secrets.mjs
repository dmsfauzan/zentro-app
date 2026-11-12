#!/usr/bin/env node
/**
 * Pemindai rahasia sederhana (tanpa dependensi).
 *
 * Tujuan: mencegah commit kredensial yang tidak sengaja (private key, token
 * provider, dsb.). BUKAN pengganti secret scanner lengkap — jalankan di CI
 * sebagai lapisan tambahan.
 *
 * Keluar dengan kode 1 bila menemukan kandidat rahasia.
 */
import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative, extname } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(fileURLToPath(new URL(".", import.meta.url)), "..");

const IGNORED_DIRS = new Set([
  "node_modules",
  "vendor",
  ".git",
  ".next",
  "dist",
  "build",
  "storage",
  "coverage",
  "playwright-report",
  "test-results",
]);

const IGNORED_EXT = new Set([
  ".png",
  ".jpg",
  ".jpeg",
  ".webp",
  ".gif",
  ".ico",
  ".pdf",
  ".zip",
  ".lock",
  ".woff",
  ".woff2",
]);

const PATTERNS = [
  { name: "Private key", re: /-----BEGIN (?:RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----/ },
  { name: "Stripe live key", re: /\bsk_live_[0-9a-zA-Z]{16,}/ },
  { name: "AWS access key", re: /\bAKIA[0-9A-Z]{16}\b/ },
  { name: "GitHub token", re: /\bgh[pousr]_[0-9A-Za-z]{36,}\b/ },
  { name: "Midtrans server key", re: /\b(?:SB-)?Mid-server-[0-9A-Za-z]{10,}\b/ },
  { name: "Google API key", re: /\bAIza[0-9A-Za-z\-_]{35}\b/ },
];

let findings = 0;

function scan(file) {
  let content;
  try {
    content = readFileSync(file, "utf8");
  } catch {
    return;
  }

  for (const { name, re } of PATTERNS) {
    const match = re.exec(content);
    if (match) {
      findings += 1;
      console.error(`[SECRET] ${name} → ${relative(root, file)}`);
    }
  }
}

function walk(dir) {
  for (const entry of readdirSync(dir)) {
    if (IGNORED_DIRS.has(entry)) continue;
    const full = join(dir, entry);
    const stat = statSync(full);

    if (stat.isDirectory()) {
      walk(full);
    } else if (!IGNORED_EXT.has(extname(entry))) {
      scan(full);
    }
  }
}

walk(root);

if (findings > 0) {
  console.error(`\nDitemukan ${findings} kandidat rahasia. Hapus/rotasi sebelum commit.`);
  process.exit(1);
}

console.log("OK: tidak ada kandidat rahasia terdeteksi.");
