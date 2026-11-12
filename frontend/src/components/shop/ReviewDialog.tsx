"use client";

import { useState } from "react";
import { Star } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { Modal } from "@/components/ui/Modal";
import { Textarea } from "@/components/ui/Input";
import { useCreateReview } from "@/features/reviews";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import type { OrderItem } from "@/lib/api/domain";

export function ReviewDialog({
  orderNumber,
  item,
  open,
  onClose,
  onDone,
}: {
  orderNumber: string;
  item: OrderItem | null;
  open: boolean;
  onClose: () => void;
  onDone: (itemId: number) => void;
}) {
  const create = useCreateReview(orderNumber);
  const toast = useToast();
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState("");

  return (
    <Modal
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
      title="Beri ulasan"
      description={item?.product_name}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>Batal</Button>
          <Button
            loading={create.isPending}
            disabled={!item}
            onClick={() => {
              if (!item) return;
              create.mutate(
                { order_item_id: item.id, rating, comment: comment || null },
                {
                  onSuccess: () => {
                    toast.success("Ulasan terkirim");
                    onDone(item.id);
                    setComment("");
                    setRating(5);
                    onClose();
                  },
                  onError: (caught) => toast.error("Gagal", errorMessage(caught)),
                },
              );
            }}
          >
            Kirim ulasan
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div>
          <p className="text-[13px] font-medium text-ink">Rating</p>
          <div className="mt-1.5 flex items-center gap-1">
            {Array.from({ length: 5 }).map((_, index) => {
              const value = index + 1;

              return (
                <button
                  key={value}
                  type="button"
                  onClick={() => setRating(value)}
                  className="rounded p-0.5"
                  aria-label={`${value} bintang`}
                  aria-pressed={rating === value}
                >
                  <Icon
                    as={Star}
                    size="md"
                    fill={value <= rating}
                    className={value <= rating ? "text-ink" : "text-line-strong"}
                  />
                </button>
              );
            })}
            <span className="num ml-1 text-sm text-ink-muted">{rating}/5</span>
          </div>
        </div>

        <div>
          <label htmlFor="review-comment" className="text-[13px] font-medium text-ink">
            Komentar
          </label>
          <div className="mt-1.5">
            <Textarea
              id="review-comment"
              value={comment}
              onChange={(event) => setComment(event.target.value)}
              placeholder="Ceritakan pengalamanmu (opsional)."
            />
          </div>
        </div>
      </div>
    </Modal>
  );
}
