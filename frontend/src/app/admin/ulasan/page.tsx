"use client";

import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { EmptyState } from "@/components/ui/EmptyState";
import { Select } from "@/components/ui/Input";
import { Pagination } from "@/components/ui/Pagination";
import { Skeleton } from "@/components/ui/Skeleton";
import { StatusBadge } from "@/components/ui/Badge";
import { PageHeader } from "@/components/ui/PageHeader";
import { TBody, TD, TH, THead, TR, TableWrap } from "@/components/ui/Table";
import { useAdminReviews, useModerateReview } from "@/features/admin";
import { useToast } from "@/providers/ToastProvider";
import { errorMessage } from "@/lib/api/client";
import { useUrlFilters } from "@/lib/use-url-filters";
import type { AdminReview } from "@/lib/api/domain";

const statuses = [
  { value: "", label: "Semua status" },
  { value: "published", label: "Tayang" },
  { value: "hidden", label: "Disembunyikan" },
  { value: "pending", label: "Menunggu" },
];

export default function AdminReviewsPage() {
  const [filters, setFilters] = useUrlFilters({ status: "", page: "1" });
  const reviews = useAdminReviews({
    status: filters.status || undefined,
    page: filters.page,
    per_page: 20,
  });
  const moderate = useModerateReview();
  const toast = useToast();

  const items = reviews.data?.items ?? [];
  const pagination = reviews.data?.pagination;

  const setStatus = (review: AdminReview, status: "published" | "hidden") =>
    moderate.mutate(
      { id: review.id, status },
      {
        onSuccess: () => toast.success(status === "published" ? "Ulasan ditayangkan" : "Ulasan disembunyikan"),
        onError: (caught) => toast.error("Gagal", errorMessage(caught)),
      },
    );

  return (
    <div className="space-y-5">
      <PageHeader
        title="Moderasi ulasan"
        description="Sembunyikan ulasan yang melanggar; rating produk & toko dihitung ulang otomatis."
        eyebrow="Admin"
      />

      <div>
        <Select
          aria-label="Status"
          className="h-9 w-44"
          value={filters.status}
          onChange={(event) => setFilters({ status: event.target.value, page: "1" })}
        >
          {statuses.map((status) => (
            <option key={status.value} value={status.value}>
              {status.label}
            </option>
          ))}
        </Select>
      </div>

      {reviews.isPending ? (
        <Skeleton className="h-64 w-full" />
      ) : items.length === 0 ? (
        <EmptyState title="Belum ada ulasan" description="Tidak ada ulasan pada filter ini." />
      ) : (
        <Card className="overflow-hidden">
          <TableWrap>
            <THead>
              <TR>
                <TH>Produk</TH>
                <TH>Pembeli</TH>
                <TH numeric>Rating</TH>
                <TH>Komentar</TH>
                <TH>Status</TH>
                <TH />
              </TR>
            </THead>
            <TBody>
              {items.map((review) => (
                <TR key={review.id}>
                  <TD>
                    <p className="line-clamp-1 text-sm font-medium text-ink">
                      {review.product?.name ?? "-"}
                    </p>
                    <p className="text-xs text-ink-subtle">{review.seller?.store_name ?? "-"}</p>
                  </TD>
                  <TD className="text-ink-muted">{review.user?.name ?? "-"}</TD>
                  <TD numeric>{review.rating} / 5</TD>
                  <TD className="max-w-xs">
                    <p className="line-clamp-2 text-sm text-ink-muted">{review.comment ?? "—"}</p>
                  </TD>
                  <TD>
                    <StatusBadge domain="review" value={review.status} />
                  </TD>
                  <TD>
                    <div className="flex justify-end">
                      {review.status === "published" ? (
                        <Button
                          size="xs"
                          variant="ghost"
                          loading={moderate.isPending}
                          onClick={() => setStatus(review, "hidden")}
                        >
                          Sembunyikan
                        </Button>
                      ) : (
                        <Button
                          size="xs"
                          loading={moderate.isPending}
                          onClick={() => setStatus(review, "published")}
                        >
                          Tayangkan
                        </Button>
                      )}
                    </div>
                  </TD>
                </TR>
              ))}
            </TBody>
          </TableWrap>
        </Card>
      )}

      {pagination ? (
        <Pagination
          page={pagination.page}
          lastPage={pagination.last_page}
          onChange={(page) => setFilters({ page: String(page) })}
        />
      ) : null}
    </div>
  );
}
