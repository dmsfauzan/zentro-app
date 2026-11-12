"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Field, Input } from "@/components/ui/Input";
import { Alert } from "@/components/ui/Alert";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";

export default function RegisterPage() {
  const { isDev, registerFirebase, user } = useAuth();
  const router = useRouter();
  const toast = useToast();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (user) {
    router.replace("/");
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);

    try {
      await registerFirebase(name, email, password);
      toast.success("Akun dibuat");
      router.replace("/");
    } catch (caught) {
      setError(errorMessage(caught));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card className="p-6 sm:p-8">
      <p className="text-xs font-medium uppercase tracking-[0.16em] text-ink-subtle">
        Daftar
      </p>
      <h1 className="mt-2 font-display text-3xl tracking-[-0.01em] text-ink">
        Buat akun ZENTRO
      </h1>

      {!isDev ? (
        <form className="mt-6 space-y-4" onSubmit={submit}>
          {error ? <Alert tone="danger" title="Gagal mendaftar">{error}</Alert> : null}
          <Field label="Nama lengkap" htmlFor="name">
            <Input
              id="name"
              value={name}
              onChange={(event) => setName(event.target.value)}
              required
            />
          </Field>
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
          <Field label="Kata sandi" htmlFor="password" hint="Minimal 6 karakter.">
            <Input
              id="password"
              type="password"
              autoComplete="new-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              minLength={6}
              required
            />
          </Field>
          <Button type="submit" className="w-full" loading={busy}>
            Daftar
          </Button>
        </form>
      ) : (
        <div className="mt-5">
          <Alert tone="info" title="Pendaftaran email memakai Firebase">
            Di lingkungan lokal, gunakan akun demo pada halaman masuk. Pendaftaran
            email aktif setelah kredensial Firebase diisi.
          </Alert>
        </div>
      )}

      <p className="mt-5 text-sm text-ink-muted">
        Sudah punya akun?{" "}
        <Link href="/login" className="font-medium text-brand hover:underline">
          Masuk
        </Link>
      </p>
    </Card>
  );
}
