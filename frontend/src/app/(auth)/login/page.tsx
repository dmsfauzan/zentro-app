"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { Store, ShieldCheck, UserRound } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Field, Input } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { Alert } from "@/components/ui/Alert";
import { Skeleton } from "@/components/ui/Skeleton";
import { useToast } from "@/providers/ToastProvider";
import { useAuth } from "@/providers/AuthProvider";
import { isFirebaseConfigured, API_BASE_URL } from "@/lib/config";
import { errorMessage } from "@/lib/api/client";

const demoAccounts = [
  { uid: "demo-customer", label: "Customer", description: "Belanja & pesanan", icon: UserRound },
  { uid: "demo-seller", label: "Seller", description: "Toko & pengiriman", icon: Store },
  { uid: "demo-admin", label: "Admin", description: "Moderasi & finance", icon: ShieldCheck },
  { uid: "demo-super-admin", label: "Super Admin", description: "Semua akses", icon: ShieldCheck },
];

function nextPath(): string {
  if (typeof window === "undefined") return "/";
  const next = new URLSearchParams(window.location.search).get("next");

  return next && next.startsWith("/") && !next.startsWith("//") ? next : "/";
}

function homeFor(roles: string[]): string {
  if (roles.includes("super_admin") || roles.includes("admin")) return "/admin";
  if (roles.includes("seller")) return "/seller";

  return "/";
}

export default function LoginPage() {
  const { user, isDev, loading, loginDev, loginFirebase, resetPassword, logout } = useAuth();
  const router = useRouter();
  const toast = useToast();
  const [busy, setBusy] = useState<string | null>(null);
  const [resetBusy, setResetBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  // Redirect otomatis hanya SETELAH login eksplisit dari halaman ini. Bila sesi
  // sudah ada saat halaman dibuka, tampilkan pilihan akun agar pengguna tidak
  // terasa "masuk sendiri" tanpa bisa mengganti akun.
  const allowRedirect = useRef(false);

  useEffect(() => {
    if (!loading && user && allowRedirect.current) {
      const target = nextPath();
      router.replace(target !== "/" ? target : homeFor(user.roles));
    }
  }, [loading, user, router]);

  const continueAs = (roles: string[]) => {
    const target = nextPath();
    router.replace(target !== "/" ? target : homeFor(roles));
  };

  const forgotPassword = async () => {
    if (!email.trim()) {
      setError("Isi email terlebih dahulu untuk mengirim tautan reset.");
      return;
    }

    setResetBusy(true);
    setError(null);

    try {
      await resetPassword(email.trim());
      toast.success("Tautan reset kata sandi dikirim ke email");
    } catch (caught) {
      setError(errorMessage(caught));
    } finally {
      setResetBusy(false);
    }
  };

  const run = async (key: string, action: () => Promise<void>, success: string) => {
    setBusy(key);
    setError(null);

    try {
      await action();
      allowRedirect.current = true;
      toast.success(success);
    } catch (caught) {
      setError(errorMessage(caught));
    } finally {
      setBusy(null);
    }
  };

  if (loading) {
    return (
      <Card className="p-6 sm:p-8">
        <Skeleton className="h-3 w-16" />
        <Skeleton className="mt-4 h-8 w-64" />
        <Skeleton className="mt-4 h-4 w-full" />
        <Skeleton className="mt-6 h-40 w-full" />
      </Card>
    );
  }

  if (user) {
    return (
      <Card className="p-6 sm:p-8">
        <p className="text-xs font-medium uppercase tracking-[0.16em] text-ink-subtle">
          Masuk
        </p>
        <h1 className="mt-2 font-display text-3xl tracking-[-0.01em] text-ink">
          Selamat datang kembali
        </h1>
        <p className="mt-1 text-sm text-ink-muted">Kamu sudah masuk di perangkat ini.</p>

        <div className="mt-5 rounded-[8px] border border-line bg-canvas px-3 py-3">
          <p className="text-sm font-medium text-ink">{user.name}</p>
          <p className="truncate text-xs text-ink-subtle">{user.email}</p>
        </div>

        <div className="mt-5 flex flex-col gap-2 sm:flex-row">
          <Button className="w-full" onClick={() => continueAs(user.roles)}>
            Lanjutkan
          </Button>
          <Button
            variant="secondary"
            className="w-full"
            onClick={() => {
              logout();
              toast.success("Kamu sudah keluar");
            }}
          >
            Ganti akun
          </Button>
        </div>

        {isDev ? (
          <p className="mt-4 text-xs text-ink-subtle">
            Mode demo lokal aktif. Setelah keluar kamu bisa memilih akun demo lain.
          </p>
        ) : null}
      </Card>
    );
  }

  return (
    <Card className="p-6 sm:p-8">
      <p className="text-xs font-medium uppercase tracking-[0.16em] text-ink-subtle">
        Masuk
      </p>
      <h1 className="mt-2 font-display text-3xl tracking-[-0.01em] text-ink">
        Selamat datang kembali
      </h1>
      <p className="mt-1 text-sm text-ink-muted">
        Masuk untuk melanjutkan belanja atau mengelola toko.
      </p>

      {error ? (
        <div className="mt-4">
          <Alert tone="danger" title="Gagal masuk">
            {error}
            {isDev ? (
              <p className="mt-1 text-xs">
                Pastikan backend berjalan di{" "}
                <span className="font-mono">{API_BASE_URL}</span> dan CORS
                mengizinkan <span className="font-mono">http://localhost:3000</span>.
              </p>
            ) : null}
          </Alert>
        </div>
      ) : null}

      {isDev ? (
        <div className="mt-6 space-y-2">
          <p className="text-xs font-medium uppercase tracking-[0.12em] text-ink-subtle">
            Akun demo (lokal)
          </p>
          {demoAccounts.map((account) => (
            <button
              key={account.uid}
              type="button"
              onClick={() =>
                run(account.uid, () => loginDev(account.uid), `Masuk sebagai ${account.label}`)
              }
              disabled={busy !== null}
              className="flex w-full items-center gap-3 rounded-[8px] border border-line bg-canvas px-3 py-2.5 text-left transition-colors hover:border-brand/40 disabled:opacity-50"
            >
              <span className="flex size-9 items-center justify-center rounded-full bg-paper text-ink-muted">
                <Icon as={account.icon} size="sm" />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-medium text-ink">{account.label}</span>
                <span className="block text-xs text-ink-subtle">{account.description}</span>
              </span>
              {busy === account.uid ? (
                <span className="text-xs text-ink-subtle">Memproses…</span>
              ) : null}
            </button>
          ))}
          <p className="pt-1 text-xs text-ink-subtle">
            Mode demo memakai verifier lokal backend. Tidak aktif di produksi.
          </p>
          <p className="text-xs text-ink-subtle">
            API: <span className="font-mono">{API_BASE_URL}</span>
          </p>
        </div>
      ) : null}

      {isFirebaseConfigured ? (
        <form
          className="mt-6 space-y-4"
          onSubmit={(event) => {
            event.preventDefault();
            void run("firebase", () => loginFirebase(email, password), "Berhasil masuk");
          }}
        >
          <Field label="Email" htmlFor="email">
            <Input
              id="email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              required
            />
          </Field>
          <Field label="Kata sandi" htmlFor="password">
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              required
            />
          </Field>
          <Button type="submit" className="w-full" loading={busy === "firebase"}>
            Masuk
          </Button>
          <div className="flex items-center justify-between gap-3 text-sm">
            <p className="text-ink-muted">
              Belum punya akun?{" "}
              <Link href="/register" className="font-medium text-brand hover:underline">
                Daftar
              </Link>
            </p>
            <button
              type="button"
              onClick={() => void forgotPassword()}
              disabled={resetBusy}
              className="font-medium text-brand hover:underline disabled:opacity-50"
            >
              {resetBusy ? "Mengirim…" : "Lupa kata sandi?"}
            </button>
          </div>
        </form>
      ) : !isDev ? (
        <div className="mt-6">
          <Alert tone="warning" title="Firebase belum dikonfigurasi">
            Isi environment Firebase untuk mengaktifkan login email.
          </Alert>
        </div>
      ) : null}
    </Card>
  );
}
