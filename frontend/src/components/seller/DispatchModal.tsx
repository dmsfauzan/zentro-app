"use client";

import { useState } from "react";
import { Button } from "@/components/ui/Button";
import { Field, Input } from "@/components/ui/Input";
import { Modal } from "@/components/ui/Modal";
import type { DispatchPayload } from "@/features/seller";

export function DispatchModal({
  open,
  onClose,
  onSubmit,
  loading,
  title = "Kirim pesanan",
}: {
  open: boolean;
  onClose: () => void;
  onSubmit: (payload: DispatchPayload) => void;
  loading?: boolean;
  title?: string;
}) {
  const [tracking, setTracking] = useState("");
  const [provider, setProvider] = useState("jne");
  const [service, setService] = useState("REG");

  return (
    <Modal
      open={open}
      onOpenChange={(next) => { if (!next) onClose(); }}
      title={title}
      description="Masukkan resi dari kurir. Status pesanan menjadi dikirim."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button
            loading={loading}
            disabled={!tracking.trim()}
            onClick={() => onSubmit({ tracking_number: tracking.trim(), provider, service_code: service })}
          >
            Kirim
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        <Field label="Nomor resi" htmlFor="tracking" required>
          <Input id="tracking" value={tracking} onChange={(event) => setTracking(event.target.value)} placeholder="JNE123456" />
        </Field>
        <div className="grid gap-3 sm:grid-cols-2">
          <Field label="Kurir" htmlFor="provider">
            <Input id="provider" value={provider} onChange={(event) => setProvider(event.target.value)} />
          </Field>
          <Field label="Layanan" htmlFor="service">
            <Input id="service" value={service} onChange={(event) => setService(event.target.value)} />
          </Field>
        </div>
      </div>
    </Modal>
  );
}
