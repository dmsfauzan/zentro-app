import { readdirSync, readFileSync, statSync } from "node:fs";
import { join } from "node:path";

const ROOT = new URL("../src", import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, "$1");
const EMOJI =
  /[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{2B00}-\u{2BFF}\u{25A0}-\u{25FF}\u{2190}-\u{21FF}\u{FE0F}\u{1F1E6}-\u{1F1FF}]/u;

const extensions = new Set([".ts", ".tsx", ".js", ".jsx", ".mjs", ".css"]);

function walk(dir) {
  const entries = readdirSync(dir);
  let files = [];

  for (const entry of entries) {
    const full = join(dir, entry);
    const stat = statSync(full);

    if (stat.isDirectory()) {
      if (entry === "node_modules") continue;
      files = files.concat(walk(full));
      continue;
    }

    if ([...extensions].some((ext) => entry.endsWith(ext))) files.push(full);
  }

  return files;
}

const offenders = [];

for (const file of walk(ROOT)) {
  const content = readFileSync(file, "utf8");
  content.split(/\r?\n/).forEach((line, index) => {
    if (EMOJI.test(line)) {
      offenders.push(`${file.replace(ROOT, "src")}:${index + 1}: ${line.trim()}`);
    }
  });
}

if (offenders.length > 0) {
  console.error("Ditemukan emoji/glyph terlarang. Gunakan komponen Icon (Lucide).\n");
  for (const offender of offenders) console.error(" - " + offender);
  process.exit(1);
}

console.log("OK: tidak ada emoji di src/.");
