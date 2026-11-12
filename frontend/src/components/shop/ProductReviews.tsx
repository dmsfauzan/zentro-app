"use client";

import { Star } from "lucide-react";
import { Icon } from "@/components/ui/Icon";
import { Skeleton } from "@/components/ui/Skeleton";
import { EmptyState } from "@/components/ui/EmptyState";
import { useProductReviews } from "@/features/reviews";
import { formatDate } from "@/lib/format";

export function ProductReviews({ slug }: { slug: string }) {
  const reviews = useProductReviews(slug);
  const items = reviews.data?.items ?? [];

  return (
    <section className="mt-10 border-t border-line pt-6">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold text-ink">Ulasan pembeli</h2>
        <span className="num text-sm text-ink-subtle">
          {reviews.data?.pagination.total ?? 0} ulasan
        </span>
      </div>

      {reviews.isPending ? (
        <div className="mt-4 space-y-3">
          <Skeleton className="h-16 w-full" />
          <Skeleton className="h-16 w-full" />
        </div>
      ) : items.length === 0 ? (
        <div className="mt-4">
          <EmptyState
            title="Belum ada ulasan"
            description="Ulasan muncul setelah pembeli menyelesaikan pesanan."
          />
        </div>
      ) : (
        <ul className="mt-4 divide-y divide-line">
          {items.map((review) => (
            <li key={review.id} className="py-3">
              <div className="flex flex-wrap items-center gap-2">
                <span className="inline-flex items-center gap-0.5" aria-label={`${review.rating} dari 5`}>
                  {Array.from({ length: 5 }).map((_, index) => (
                    <Icon
                      key={index}
                      as={Star}
                      size="xs"
                      fill={index < review.rating}
                      className={index < review.rating ? "text-ink" : "text-line-strong"}
                    />
                  ))}
                </span>
                <span className="text-sm font-medium text-ink">
                  {review.user?.name ?? "Pembeli"}
                </span>
                <span className="text-xs text-ink-subtle">{formatDate(review.created_at)}</span>
              </div>
              {review.comment ? (
                <p className="mt-1.5 max-w-[65ch] text-sm leading-6 text-ink-muted">
                  {review.comment}
                </p>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
