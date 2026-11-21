"use client";

import Link from "next/link";
import { useState } from "react";
import { MapPin } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { Field, Input } from "@/components/ui/Input";
import { Icon } from "@/components/ui/Icon";
import { Skeleton } from "@/components/ui/Skeleton";
import { useMe, useUpdateMe } from "@/features/account";
import { useToast } from "@/providers/ToastProvider";
import { useAuth } from "@/providers/AuthProvider";
import { errorMessage } from "@/lib/api/client";
import { isFirebaseConfigured } from "@/lib/config";

export default function AccountPage() {
  const me = useMe();
  const update = useUpdateMe();
  const toast = useToast();
  const { changePassword } = useAuth();
  const [passwordBusy, setPasswordBusy] = useState(false);
  const [passwordError, setPasswordError] = useState<string | null>(null);

  if (me.isPending) {
    return <div className="container-page py-8"><Skeleton className="h-64 w-full" /></div>;
  }

  if (!me.data) {
    return (
      <div className="container-page py-14">
        <Alert tone="warning" title="Masuk untuk membuka akun">
          <Link href="/login?next=/akun" className="font-medium underline">Masuk</Link>
        </Alert>
      </div>
    );
  }

  return (
    <div className="container-page py-8">
      <h1 className="text-2xl font-semibold tracking-[-0.01em] text-ink">Akun</h1>

      <div className="mt-6 grid gap-6 lg:grid-cols-12">
        <div className="lg:col-span-7">
          <Card>
            <CardHeader title="Profil" description="Nama dan nomor telepon yang dipakai di pesanan." />
            <CardBody>
              <form
                className="space-y-4"
                onSubmit={(event) => {
                  event.preventDefault();
                  const data = new FormData(event.currentTarget);

                  update.mutate(
                    {
                      name: String(data.get("name") ?? ""),
                      phone: String(data.get("phone") ?? "") || null,
                    },
                    {
                      onSuccess: () => toast.success("Profil diperbarui"),
                      onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                    },
                  );
                }}
              >
                <Field label="Email" htmlFor="email" hint="Email terhubung dengan identitas login.">
                  <Input id="email" defaultValue={me.data.email} disabled />
                </Field>
                <Field label="Nama" htmlFor="name">
                  <Input id="name" name="name" defaultValue={me.data.name} required />
                </Field>
                <Field label="Telepon" htmlFor="phone">
                  <Input id="phone" name="phone" defaultValue={me.data.phone ?? ""} />
                </Field>
                <Button type="submit" loading={update.isPending}>Simpan perubahan</Button>
              </form>
            </CardBody>
          </Card>
        </div>

        <div className="space-y-4 lg:col-span-5">
          <Card>
            <CardHeader title="Akses akun" />
            <CardBody className="space-y-2 text-sm">
              <div className="flex justify-between gap-4">
                <span className="text-ink-muted">Peran</span>
                <span className="text-ink">{me.data.roles.join(", ") || "-"}</span>
              </div>
              <div className="flex justify-between gap-4">
                <span className="text-ink-muted">Status</span>
                <span className="text-ink">{me.data.status}</span>
              </div>
            </CardBody>
          </Card>

          {isFirebaseConfigured ? (
            <Card>
              <CardHeader
                title="Keamanan"
                description="Ganti kata sandi akun email kamu."
              />
              <CardBody>
                {passwordError ? (
                  <Alert tone="danger" title="Gagal mengganti kata sandi">
                    {passwordError}
                  </Alert>
                ) : null}
                <form
                  className="space-y-4"
                  onSubmit={(event) => {
                    event.preventDefault();
                    const form = event.currentTarget;
                    const data = new FormData(form);
                    const currentPassword = String(data.get("currentPassword") ?? "");
                    const newPassword = String(data.get("newPassword") ?? "");
                    const confirmation = String(data.get("confirmation") ?? "");

                    if (newPassword.length < 6) {
                      setPasswordError("Kata sandi baru minimal 6 karakter.");
                      return;
                    }

                    if (newPassword !== confirmation) {
                      setPasswordError("Konfirmasi kata sandi tidak cocok.");
                      return;
                    }

                    setPasswordBusy(true);
                    setPasswordError(null);
                    void changePassword(currentPassword, newPassword)
                      .then(() => {
                        form.reset();
                        toast.success("Kata sandi berhasil diganti");
                      })
                      .catch((caught) => setPasswordError(errorMessage(caught)))
                      .finally(() => setPasswordBusy(false));
                  }}
                >
                  <Field label="Kata sandi saat ini" htmlFor="currentPassword">
                    <Input
                      id="currentPassword"
                      name="currentPassword"
                      type="password"
                      autoComplete="current-password"
                      required
                    />
                  </Field>
                  <Field label="Kata sandi baru" htmlFor="newPassword" hint="Minimal 6 karakter.">
                    <Input
                      id="newPassword"
                      name="newPassword"
                      type="password"
                      autoComplete="new-password"
                      minLength={6}
                      required
                    />
                  </Field>
                  <Field label="Konfirmasi kata sandi" htmlFor="confirmation">
                    <Input
                      id="confirmation"
                      name="confirmation"
                      type="password"
                      autoComplete="new-password"
                      minLength={6}
                      required
                    />
                  </Field>
                  <Button type="submit" loading={passwordBusy}>
                    Ganti kata sandi
                  </Button>
                </form>
              </CardBody>
            </Card>
          ) : null}

          <Card>
            <CardHeader title="Alamat" />
            <CardBody>
              <Link
                href="/akun/alamat"
                className="inline-flex items-center gap-2 text-sm font-medium text-brand hover:underline"
              >
                <Icon as={MapPin} size="xs" />
                Kelola alamat pengiriman
              </Link>
            </CardBody>
          </Card>
        </div>
      </div>
    </div>
  );
}
