import { API_BASE_URL } from "@/lib/config";
import type { ApiEnvelope, ApiMeta } from "@/lib/api/types";

/**
 * Error terstruktur dari API ZENTRO.
 */
export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly errors: Record<string, unknown> = {},
    public readonly code?: string,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export interface ApiFetchOptions {
  method?: "GET" | "POST" | "PATCH" | "PUT" | "DELETE";
  /** Body akan di-JSON.stringify. */
  json?: unknown;
  /** Untuk upload multipart (FormData). */
  formData?: FormData;
  token?: string | null;
  headers?: Record<string, string>;
  signal?: AbortSignal;
  timeoutMs?: number;
  idempotencyKey?: string;
}

export interface ApiResult<T> {
  data: T;
  meta?: ApiMeta;
  status: number;
  replayed: boolean;
}

const DEFAULT_TIMEOUT = 20_000;

/**
 * API client terpusat: base URL, token, timeout, envelope, dan error.
 */
export async function apiFetchFull<T>(
  path: string,
  options: ApiFetchOptions = {},
): Promise<ApiResult<T>> {
  const {
    method = "GET",
    json,
    formData,
    token,
    headers,
    signal,
    timeoutMs = DEFAULT_TIMEOUT,
    idempotencyKey,
  } = options;

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  if (signal) {
    signal.addEventListener("abort", () => controller.abort(), { once: true });
  }

  try {
    const response = await fetch(`${API_BASE_URL}${path}`, {
      method,
      headers: {
        Accept: "application/json",
        ...(json !== undefined ? { "Content-Type": "application/json" } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(idempotencyKey ? { "Idempotency-Key": idempotencyKey } : {}),
        ...headers,
      },
      body: formData ?? (json !== undefined ? JSON.stringify(json) : undefined),
      cache: "no-store",
      signal: controller.signal,
    });

    const payload = (await response
      .json()
      .catch(() => null)) as ApiEnvelope<T> | null;

    if (!response.ok || !payload?.success) {
      const code =
        payload?.errors && typeof payload.errors.code === "string"
          ? payload.errors.code
          : undefined;

      throw new ApiError(
        payload?.message ?? `Permintaan gagal (HTTP ${response.status}).`,
        response.status,
        payload?.errors ?? {},
        code,
      );
    }

    return {
      data: payload.data,
      meta: payload.meta,
      status: response.status,
      replayed: response.headers.get("X-Idempotent-Replayed") === "true",
    };
  } catch (error) {
    if (error instanceof ApiError) throw error;

    if (error instanceof DOMException && error.name === "AbortError") {
      throw new ApiError("Permintaan melebihi batas waktu.", 408);
    }

    // TypeError "Failed to fetch" = jaringan/CORS/backend mati.
    if (error instanceof TypeError || (error instanceof Error && /fetch/i.test(error.message))) {
      throw new ApiError(
        `Tidak dapat menghubungi server API (${API_BASE_URL}). Pastikan backend berjalan.`,
        0,
      );
    }

    throw new ApiError(
      error instanceof Error
        ? error.message
        : "Tidak dapat menghubungi server.",
      0,
    );
  } finally {
    clearTimeout(timeout);
  }
}

/** Selalu mengembalikan `data` dari envelope atau melempar `ApiError`. */
export async function apiFetch<T>(
  path: string,
  options: ApiFetchOptions = {},
): Promise<T> {
  const result = await apiFetchFull<T>(path, options);

  return result.data;
}

/** Ambil pesan error per-field dari response 422. */
export function fieldErrors(errors: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};

  for (const [key, value] of Object.entries(errors)) {
    if (key === "code") continue;
    if (Array.isArray(value) && typeof value[0] === "string") {
      result[key] = value[0];
    } else if (typeof value === "string") {
      result[key] = value;
    }
  }

  return result;
}

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) return error.message;
  if (error instanceof Error) return error.message;

  return "Terjadi kesalahan.";
}
