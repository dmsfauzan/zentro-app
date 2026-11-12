import Link from "next/link";
import { Money } from "@/components/ui/Money";
import { RatingStars } from "@/components/shop/RatingStars";
import { formatNumber } from "@/lib/format";
import type { ProductSummary } from "@/lib/api/domain";

export function ProductCard({ product }: { product: ProductSummary }) {
  const name = product.name;
  const image = product.primary_image;
  const seller = product.seller?.store_name ?? product.category?.name;

  return (
    <Link
      href={`/produk/${product.slug}`}
      className="group flex flex-col focus-visible:outline-none"
    >
      <div className="relative aspect-[4/5] overflow-hidden rounded-[16px] bg-paper">
        {image ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={image.url}
            alt={image.alt_text ?? name}
            loading="lazy"
            decoding="async"
            className="size-full object-cover transition-transform duration-200 group-hover:scale-[1.03]"
          />
        ) : (
          <span className="flex size-full items-center justify-center font-display text-3xl text-ink-subtle">
            {name.slice(0, 1).toUpperCase()}
          </span>
        )}
      </div>

      <h3 className="mt-3 line-clamp-2 text-sm font-semibold leading-5 text-ink group-hover:text-brand">
        {name}
      </h3>

      {product.rating_count > 0 ? (
        <div className="mt-1.5 flex items-center gap-1.5 text-xs text-ink-muted">
          <RatingStars value={product.rating_avg} />
          <span className="num">
            {product.rating_avg.toFixed(1)}
            <span className="text-ink-subtle">/5</span>
          </span>
        </div>
      ) : null}

      <div className="mt-1.5 flex flex-wrap items-baseline gap-x-1.5">
        <Money value={product.min_price} className="text-lg font-semibold text-ink" />
        {product.max_price > product.min_price ? (
          <span className="num text-xs text-ink-subtle">
            – <Money value={product.max_price} />
          </span>
        ) : null}
      </div>

      <div className="mt-0.5 flex items-center gap-1.5 text-xs text-ink-subtle">
        {seller ? <span className="truncate">{seller}</span> : null}
        {seller ? <span aria-hidden="true">·</span> : null}
        <span className="num">{formatNumber(product.sold_count)} terjual</span>
      </div>
    </Link>
  );
}
