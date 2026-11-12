/**
 * Tipe envelope response API ZENTRO (/api/v1).
 */

export interface ApiEnvelope<T> {
  success: boolean;
  message: string;
  data: T;
  meta?: ApiMeta;
  errors?: Record<string, unknown>;
}

export interface ApiMeta {
  pagination?: PaginationMeta;
  [key: string]: unknown;
}

export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  has_more: boolean;
}

/** Bentuk user yang dikembalikan backend (Phase 1). */
export interface User {
  id: number;
  firebase_uid: string;
  name: string;
  email: string;
  email_verified: boolean;
  phone: string | null;
  status: "active" | "inactive" | "suspended" | "banned";
  roles: string[];
  permissions: string[];
  seller: null;
  created_at: string | null;
}

export interface UserAddress {
  id: number;
  label: string | null;
  recipient_name: string;
  phone: string;
  address_line: string;
  province: string;
  city: string;
  district: string | null;
  village: string | null;
  postal_code: string;
  latitude: number | null;
  longitude: number | null;
  is_default: boolean;
  created_at: string | null;
  updated_at: string | null;
}
