"use client";

import Link from "next/link";
import { useEffect, useRef, useState, type FormEvent } from "react";
import { Bot, MessageCircle, Send, ShoppingCart, X } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Icon } from "@/components/ui/Icon";
import { useAssistantChat, type AssistantProduct, type AssistantTurn } from "@/features/assistant";
import { useAddToCart } from "@/features/cart";
import { errorMessage } from "@/lib/api/client";
import { formatRupiah } from "@/lib/format";
import { useAuth } from "@/providers/AuthProvider";
import { useToast } from "@/providers/ToastProvider";

interface Message extends AssistantTurn {
  id: number;
  products?: AssistantProduct[];
}

const welcome: Message = {
  id: 0,
  role: "assistant",
  content: "Halo, saya asisten belanja ZENTRO. Produk apa yang sedang kamu cari?",
};

export function ShoppingAssistant() {
  const [open, setOpen] = useState(false);
  const [messages, setMessages] = useState<Message[]>([welcome]);
  const [input, setInput] = useState("");
  const nextId = useRef(1);
  const bottomRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLTextAreaElement>(null);
  const chat = useAssistantChat();
  const addToCart = useAddToCart();
  const { token } = useAuth();
  const toast = useToast();

  useEffect(() => {
    if (!open) return;

    bottomRef.current?.scrollIntoView({ behavior: "smooth", block: "end" });
  }, [open, messages, chat.isPending]);

  // Kolom mulai kecil, tumbuh ke bawah seiring pesan, lalu scroll pada ~10 baris.
  useEffect(() => {
    const field = inputRef.current;

    if (!field) return;

    field.style.height = "auto";
    field.style.height = `${Math.min(field.scrollHeight, 220)}px`;
  }, [input]);

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const message = input.trim();

    if (!message || chat.isPending) return;

    const userMessage: Message = { id: nextId.current++, role: "user", content: message };
    const history = messages
      .filter((item) => item.id !== 0)
      .slice(-6)
      .map(({ role, content }) => ({ role, content }));

    setMessages((current) => [...current, userMessage]);
    setInput("");

    chat.mutate(
      { message, history },
      {
        onSuccess: (response) => {
          setMessages((current) => [
            ...current,
            {
              id: nextId.current++,
              role: "assistant",
              content: response.reply,
              products: response.products,
            },
          ]);
        },
        onError: (caught) => {
          setMessages((current) => [
            ...current,
            {
              id: nextId.current++,
              role: "assistant",
              content: `Maaf, asisten sedang bermasalah. ${errorMessage(caught)}`,
            },
          ]);
        },
      },
    );
  };

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        className="fixed bottom-5 right-5 z-40 inline-flex size-14 items-center justify-center rounded-full bg-brand text-white shadow-[var(--shadow-e2)] hover:bg-brand-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
        aria-label={open ? "Tutup asisten belanja" : "Buka asisten belanja"}
        aria-expanded={open}
      >
        <Icon as={open ? X : MessageCircle} size="xl" />
      </button>

      {open ? (
        <section
          aria-label="Asisten belanja"
          className="fixed bottom-24 right-4 z-40 flex h-[min(620px,calc(100vh-120px))] w-[min(390px,calc(100vw-32px))] flex-col overflow-hidden rounded-[16px] border border-line bg-canvas shadow-[var(--shadow-e2)]"
        >
          <header className="flex items-center gap-3 border-b border-line bg-ink px-4 py-3 text-white">
            <span className="flex size-9 items-center justify-center rounded-full bg-white/10">
              <Icon as={Bot} size="md" />
            </span>
            <div>
              <h2 className="text-sm font-semibold">Asisten belanja</h2>
              <p className="text-xs text-white/70">Cari produk lebih cepat</p>
            </div>
          </header>

          <div className="flex-1 space-y-4 overflow-y-auto p-4" aria-live="polite">
            {messages.map((message) => (
              <div key={message.id} className="space-y-3">
                <div
                  className={`max-w-[88%] rounded-[12px] px-3 py-2 text-sm leading-5 ${
                    message.role === "user"
                      ? "ml-auto bg-brand text-white"
                      : "bg-paper text-ink"
                  }`}
                >
                  {message.content}
                </div>

                {message.products?.length ? (
                  <div className="space-y-2">
                    {message.products.map((product) => (
                      <AssistantProductCard
                        key={product.id}
                        product={product}
                        loggedIn={Boolean(token)}
                        adding={addToCart.isPending}
                        onAdd={(variantId) => {
                          addToCart.mutate(
                            { variant_id: variantId, quantity: 1 },
                            {
                              onSuccess: () => toast.success("Produk masuk keranjang"),
                              onError: (caught) =>
                                toast.error("Gagal menambah produk", errorMessage(caught)),
                            },
                          );
                        }}
                      />
                    ))}
                  </div>
                ) : null}
              </div>
            ))}

            {chat.isPending ? (
              <div className="w-fit rounded-[12px] bg-paper px-3 py-2 text-sm text-ink-muted">
                Sedang mengetik…
              </div>
            ) : null}

            <div ref={bottomRef} aria-hidden="true" />
          </div>

          <form onSubmit={submit} className="flex items-end gap-2 border-t border-line p-3">
            <label htmlFor="assistant-message" className="sr-only">
              Pesan untuk asisten
            </label>
            <textarea
              ref={inputRef}
              id="assistant-message"
              value={input}
              onChange={(event) => setInput(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Enter" && !event.shiftKey) {
                  event.preventDefault();
                  event.currentTarget.form?.requestSubmit();
                }
              }}
              rows={1}
              maxLength={500}
              autoComplete="off"
              placeholder="Contoh: sepatu di bawah 300 ribu"
              className="max-h-[220px] min-w-0 flex-1 resize-none overflow-y-auto rounded-[20px] border border-line bg-canvas px-4 py-2.5 text-sm leading-5 text-ink outline-none placeholder:text-ink-subtle focus:border-brand"
            />
            <button
              type="submit"
              disabled={!input.trim() || chat.isPending}
              className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-brand text-white disabled:opacity-50"
              aria-label="Kirim pesan"
            >
              <Icon as={Send} size="sm" />
            </button>
          </form>
        </section>
      ) : null}
    </>
  );
}

function AssistantProductCard({
  product,
  loggedIn,
  adding,
  onAdd,
}: {
  product: AssistantProduct;
  loggedIn: boolean;
  adding: boolean;
  onAdd: (variantId: number) => void;
}) {
  const variant = product.default_variant;

  return (
    <article className="rounded-[12px] border border-line bg-canvas p-3">
      <div className="flex gap-3">
        <Link href={`/produk/${product.slug}`} className="size-16 shrink-0 overflow-hidden rounded-[8px] bg-paper">
          {product.primary_image ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={product.primary_image.url}
              alt={product.primary_image.alt_text ?? product.name}
              className="size-full object-cover"
            />
          ) : (
            <span className="flex size-full items-center justify-center font-display text-xl text-ink-subtle">
              {product.name.slice(0, 1).toUpperCase()}
            </span>
          )}
        </Link>
        <div className="min-w-0 flex-1">
          <Link
            href={`/produk/${product.slug}`}
            className="line-clamp-2 text-sm font-semibold text-ink hover:text-brand"
          >
            {product.name}
          </Link>
          <p className="mt-1 text-sm font-semibold text-brand">
            {formatRupiah(variant?.price ?? product.min_price)}
          </p>
          {variant?.variant_name ? (
            <p className="truncate text-xs text-ink-subtle">{variant.variant_name}</p>
          ) : null}
        </div>
      </div>

      {loggedIn && variant ? (
        <Button
          type="button"
          size="xs"
          className="mt-3 w-full"
          disabled={adding}
          onClick={() => onAdd(variant.id)}
        >
          <Icon as={ShoppingCart} size="xs" />
          Tambah ke keranjang
        </Button>
      ) : !loggedIn ? (
        <Link
          href="/login"
          className="mt-3 flex h-8 items-center justify-center rounded-full border border-line-strong text-[13px] font-semibold text-ink hover:border-brand hover:text-brand"
        >
          Masuk untuk membeli
        </Link>
      ) : (
        <p className="mt-3 text-xs text-ink-subtle">Stok sedang tidak tersedia.</p>
      )}
    </article>
  );
}
