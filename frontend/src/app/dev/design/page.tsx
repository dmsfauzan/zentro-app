import { notFound } from "next/navigation";
import { PackageSearch } from "lucide-react";
import { Alert } from "@/components/ui/Alert";
import { Badge, StatusBadge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { Card, CardBody, CardHeader } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Field, Input, Select, Textarea } from "@/components/ui/Input";
import { Money } from "@/components/ui/Money";
import { Skeleton, SkeletonText } from "@/components/ui/Skeleton";

/**
 * Galeri desain (non-produksi): token, tipografi, komponen & state.
 * Gerbang review sebelum menambah layar baru.
 */
export default function DesignSystemPage() {
  if (process.env.NODE_ENV === "production") notFound();

  const colors = [
    ["paper", "#F6F7F6"],
    ["canvas", "#FFFFFF"],
    ["ink", "#052440"],
    ["ink-muted", "#4A5C6B"],
    ["line", "#E3E7E6"],
    ["brand", "#0875E2"],
    ["brand-strong", "#0062D1"],
    ["brand-soft", "#E9F2FD"],
    ["success", "#0F7B57"],
    ["warning", "#A86400"],
    ["danger", "#B42318"],
    ["info", "#0B6BB3"],
  ] as const;

  return (
    <div className="container-page space-y-10 py-10">
      <header className="space-y-1">
        <p className="text-xs font-medium uppercase tracking-[0.16em] text-ink-subtle">
          ZENTRO · Design system
        </p>
        <h1 className="font-display text-4xl tracking-[-0.02em] text-ink">
          Kios Editorial
        </h1>
        <p className="max-w-2xl text-sm text-ink-muted">
          Kertas hangat, tinta navy, satu warna sinyal. Tanpa emoji; semua simbol
          memakai ikon Lucide 1.5px.
        </p>
      </header>

      <section className="space-y-3">
        <h2 className="text-lg font-semibold text-ink">Warna</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          {colors.map(([name, hex]) => (
            <div key={name} className="rounded-[8px] border border-line bg-canvas p-3">
              <div className="h-12 rounded-[6px] border border-line" style={{ background: hex }} />
              <p className="mt-2 text-sm font-medium text-ink">{name}</p>
              <p className="num text-xs text-ink-subtle">{hex}</p>
            </div>
          ))}
        </div>
      </section>

      <section className="space-y-3">
        <h2 className="text-lg font-semibold text-ink">Tipografi</h2>
        <Card>
          <CardBody className="space-y-3">
            <p className="font-display text-4xl tracking-[-0.02em] text-ink">
              Display serif untuk judul marketing
            </p>
            <p className="text-2xl font-semibold tracking-[-0.01em] text-ink">
              Heading 24/600
            </p>
            <p className="text-base leading-7 text-ink-muted">
              Body 16/1.6 untuk halaman customer. Angka uang selalu tabular.
            </p>
            <p className="num font-mono text-sm text-ink">ZTR-20261111-000001 · Rp 149.000</p>
          </CardBody>
        </Card>
      </section>

      <section className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader title="Tombol" />
          <CardBody className="flex flex-wrap items-center gap-2">
            <Button>Primary</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="ghost">Ghost</Button>
            <Button variant="danger">Danger</Button>
            <Button loading>Loading</Button>
            <Button disabled>Disabled</Button>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Badge & status" />
          <CardBody className="flex flex-wrap items-center gap-2">
            <Badge>Neutral</Badge>
            <Badge tone="brand">Brand</Badge>
            <Badge tone="success">Sukses</Badge>
            <Badge tone="warning">Menunggu</Badge>
            <Badge tone="danger">Gagal</Badge>
            <StatusBadge domain="order" value="paid" />
            <StatusBadge domain="shipment" value="in_transit" />
            <StatusBadge domain="payout" value="requested" />
          </CardBody>
        </Card>
      </section>

      <section className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader title="Form" />
          <CardBody className="space-y-3">
            <Field label="Nama" htmlFor="demo-name">
              <Input id="demo-name" placeholder="Nama lengkap" />
            </Field>
            <Field label="Kategori" htmlFor="demo-category">
              <Select id="demo-category">
                <option>Pilih kategori</option>
                <option>Elektronik</option>
              </Select>
            </Field>
            <Field label="Catatan" htmlFor="demo-note" error="Contoh pesan validasi.">
              <Textarea id="demo-note" invalid />
            </Field>
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="State" />
          <CardBody className="space-y-3">
            <Alert tone="info" title="Info">Informasi netral untuk pengguna.</Alert>
            <Alert tone="warning" title="Peringatan">Periksa kembali sebelum lanjut.</Alert>
            <SkeletonText lines={2} />
            <Skeleton className="h-10 w-full" />
            <EmptyState
              icon={PackageSearch}
              title="Belum ada data"
              description="Contoh empty state dengan satu aksi."
              action={<Button size="sm">Tambah</Button>}
            />
          </CardBody>
        </Card>
      </section>

      <section className="space-y-3">
        <h2 className="text-lg font-semibold text-ink">Angka & uang</h2>
        <Card>
          <CardBody className="flex flex-wrap items-baseline gap-6">
            <Money value={149000} className="text-2xl font-semibold" />
            <Money value={1250000} className="text-base" />
            <span className="num text-sm text-ink-muted">12 terjual</span>
          </CardBody>
        </Card>
      </section>
    </div>
  );
}
