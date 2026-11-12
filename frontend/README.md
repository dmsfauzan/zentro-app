# ZENTRO — Frontend

Next.js 16 (App Router, Turbopack) + React 19 + TypeScript + Tailwind CSS 4.

## Menjalankan

Prasyarat: backend Laravel berjalan di `http://localhost:8000` (lihat `../backend/README` atau root `README.md`).

```bash
cp .env.local.example .env.local
npm install
npm run dev
```

Buka `http://localhost:3000`.

### Akun demo (tanpa kredensial Firebase)

`.env.local` dengan `NEXT_PUBLIC_AUTH_DEV_MODE=true` menampilkan tombol akun demo:

| Akun | Peran |
|---|---|
| `demo-customer` | Customer |
| `demo-seller` | Seller |
| `demo-admin` | Admin |
| `demo-super-admin` | Super admin |

Backend harus berjalan dengan `FIREBASE_FAKE_VERIFIER=true` (default lokal).

## Perintah

| Perintah | Fungsi |
|---|---|
| `npm run dev` | Server pengembangan |
| `npm run build` | Build produksi |
| `npm run lint` | ESLint + gerbang bebas emoji (`scripts/check-emoji.mjs`) |
| `npm run typecheck` | `tsc --noEmit` |
| `npm run typegen` | Generate tipe route Next |
| `npm run e2e` | Playwright (butuh backend + data) |

## Arsitektur singkat

- `src/app` — route group: `(shop)`, `(auth)`, `seller`, `admin`.
- `src/components` — UI kit, layout, komponen domain.
- `src/features` — API + hook TanStack Query per domain.
- `src/lib` — API client, config, format, token, query keys.
- `src/providers` — Auth, Query, Toast.
- `src/proxy.ts` — guard UX (Next 16 menggantikan `middleware`).

## Aturan desain

- Tanpa emoji; semua simbol memakai `components/ui/Icon` (Lucide, stroke 1.5).
- Warna: brand blue untuk aksi, ink untuk teks, paper/canvas untuk permukaan, warna semantik untuk status.
- Tanpa gradient dekoratif, glassmorphism, atau bayangan berlebihan pada kartu.
- Semua uang rupiah integer, angka memakai `tabular-nums`.
- Lihat `C:\Users\PROGRAMMER02\.opencode\plan\phase-8-frontend-plan-v2.md` untuk arah desain lengkap.

## Troubleshooting

- **Login `Failed to fetch`** → backend belum berjalan. Jalankan `php artisan serve` dan pastikan `NEXT_PUBLIC_API_BASE_URL` benar.
- **Login error menyebut `127.0.0.1:6379`** → **Redis belum berjalan**. Nyalakan Redis Laragon (atau set `CACHE_STORE=file` / `QUEUE_CONNECTION=sync` untuk lokal).
- **CORS** → backend harus mengizinkan origin frontend (`CORS_ALLOWED_ORIGINS`, default `http://localhost:3000` dan `http://127.0.0.1:3000`).
- **Login lokal tanpa Firebase** → pastikan `NEXT_PUBLIC_AUTH_DEV_MODE=true` dan backend `FIREBASE_FAKE_VERIFIER=true`.

## E2E

```bash
npx playwright install chromium
npm run e2e
```

Backend: `php artisan migrate:fresh --seed`, `php artisan serve`.
