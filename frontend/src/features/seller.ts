"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/lib/api/client";
import { endpoints } from "@/lib/api/endpoints";
import type {
  Commission,
  Paginated,
  ProductDetail,
  ProductSummary,
  SellerDashboard,
  SellerOrder,
  SellerProfile,
  Shipment,
} from "@/lib/api/domain";
import { qk, type Filters } from "@/lib/query/keys";
import { getList } from "@/features/http";
import { useAuth } from "@/providers/AuthProvider";

/* ------------------------------------------------------------------ */
/* Onboarding                                                          */
/* ------------------------------------------------------------------ */

export interface SellerApplicationPayload {
  store_name: string;
  description?: string | null;
  address: {
    contact_name: string;
    phone: string;
    address_line: string;
    province: string;
    city: string;
    district?: string | null;
    village?: string | null;
    postal_code: string;
    latitude?: number | null;
    longitude?: number | null;
  };
}

export function useSellerApplication() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.application,
    queryFn: () => apiFetch<SellerProfile>(endpoints.seller.applicationMe, { token }),
    enabled: Boolean(token),
    retry: false,
  });
}

export function useSubmitSellerApplication() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SellerApplicationPayload) =>
      apiFetch<SellerProfile>(endpoints.seller.applications, {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.application });
      void queryClient.invalidateQueries({ queryKey: qk.me });
    },
  });
}

export function useUploadSellerDocument() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ sellerId, type, file }: { sellerId: number; type: string; file: File }) => {
      const form = new FormData();
      form.append("type", type);
      form.append("file", file);

      return apiFetch<{ seller: SellerProfile }>(
        endpoints.seller.documents(sellerId),
        { method: "POST", token, formData: form },
      );
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.application });
    },
  });
}

/* ------------------------------------------------------------------ */
/* Profil & dashboard                                                  */
/* ------------------------------------------------------------------ */

export function useSellerProfile() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.profile,
    queryFn: () => apiFetch<SellerProfile>(endpoints.seller.profile, { token }),
    enabled: Boolean(token),
    retry: false,
  });
}

export interface UpdateSellerProfilePayload {
  store_name?: string;
  description?: string | null;
  address?: {
    contact_name: string;
    phone: string;
    address_line: string;
    province: string;
    city: string;
    district?: string | null;
    village?: string | null;
    postal_code: string;
  };
}

export function useUpdateSellerProfile() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: UpdateSellerProfilePayload) =>
      apiFetch<SellerProfile>(endpoints.seller.profile, {
        method: "PATCH",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.profile });
    },
  });
}

export function useSellerDashboard() {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.dashboard,
    queryFn: () => apiFetch<SellerDashboard>(endpoints.seller.dashboard, { token }),
    enabled: Boolean(token),
  });
}

/* ------------------------------------------------------------------ */
/* Produk                                                              */
/* ------------------------------------------------------------------ */

export interface ProductVariantPayload {
  sku: string;
  variant_name: string;
  price: number;
  weight_gram?: number | null;
  stock?: number | null;
  low_stock_threshold?: number | null;
}

export interface CreateProductPayload {
  name: string;
  category_id: number;
  description?: string | null;
  weight_gram?: number | null;
  variants: ProductVariantPayload[];
}

export interface UpdateProductPayload {
  name?: string;
  category_id?: number;
  description?: string | null;
  weight_gram?: number | null;
  status?: "draft" | "active" | "inactive" | "archived";
}

export function useSellerProducts(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.products(filters),
    queryFn: () =>
      getList<ProductSummary>(endpoints.seller.products, token, filters),
    enabled: Boolean(token),
  });
}

export function useSellerProduct(id: number) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.product(id),
    queryFn: () => apiFetch<ProductDetail>(endpoints.seller.product(id), { token }),
    enabled: Boolean(token) && Number.isFinite(id),
  });
}

export function useCreateProduct() {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateProductPayload) =>
      apiFetch<ProductDetail>(endpoints.seller.products, {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["seller", "products"] }),
  });
}

export function useUpdateProduct(id: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: UpdateProductPayload) =>
      apiFetch<ProductDetail>(endpoints.seller.product(id), {
        method: "PATCH",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.product(id) });
      void queryClient.invalidateQueries({ queryKey: ["seller", "products"] });
    },
  });
}

export function useCreateVariant(productId: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ProductVariantPayload) =>
      apiFetch<ProductDetail>(endpoints.seller.productVariants(productId), {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.product(productId) });
    },
  });
}

export function useUpdateVariant(productId: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number;
      payload: Partial<ProductVariantPayload> & { status?: string };
    }) =>
      apiFetch(endpoints.seller.variant(id), {
        method: "PATCH",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.product(productId) });
    },
  });
}

export function useUpdateInventory(productId: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      variantId,
      payload,
    }: {
      variantId: number;
      payload: {
        mode?: "set" | "adjust";
        quantity: number;
        low_stock_threshold?: number;
        note?: string;
      };
    }) =>
      apiFetch(endpoints.seller.variantInventory(variantId), {
        method: "PATCH",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.product(productId) });
    },
  });
}

export function useUploadProductImage(productId: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (file: File) => {
      const form = new FormData();
      form.append("file", file);

      return apiFetch(endpoints.seller.productImages(productId), {
        method: "POST",
        token,
        formData: form,
      });
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.product(productId) });
    },
  });
}

export function useDeleteProductImage(productId: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (imageId: number) =>
      apiFetch<null>(endpoints.seller.productImage(productId, imageId), {
        method: "DELETE",
        token,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.product(productId) });
    },
  });
}

/* ------------------------------------------------------------------ */
/* Pesanan & pengiriman                                                */
/* ------------------------------------------------------------------ */

export function useSellerOrders(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.orders(filters),
    queryFn: () => getList<SellerOrder>(endpoints.seller.orders, token, filters),
    enabled: Boolean(token),
  });
}

export function useSellerOrder(id: number) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.order(id),
    queryFn: () => apiFetch<SellerOrder>(endpoints.seller.order(id), { token }),
    enabled: Boolean(token) && Number.isFinite(id),
  });
}

export function useConfirmSellerOrder(id: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (note?: string) =>
      apiFetch<SellerOrder>(endpoints.seller.confirmOrder(id), {
        method: "POST",
        token,
        json: note ? { note } : {},
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.order(id) });
      void queryClient.invalidateQueries({ queryKey: ["seller", "orders"] });
      void queryClient.invalidateQueries({ queryKey: qk.seller.dashboard });
    },
  });
}

export interface DispatchPayload {
  tracking_number: string;
  provider?: string;
  service_code?: string;
}

export function useDispatchSellerOrder(id: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: DispatchPayload) =>
      apiFetch<Shipment>(endpoints.seller.orderShipment(id), {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: qk.seller.order(id) });
      void queryClient.invalidateQueries({ queryKey: ["seller", "orders"] });
      void queryClient.invalidateQueries({ queryKey: ["seller", "shipments"] });
    },
  });
}

export function useSellerShipments(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.shipments(filters),
    queryFn: () => getList<Shipment>(endpoints.seller.shipments, token, filters),
    enabled: Boolean(token),
  });
}

export function useSellerShipment(id: number) {
  const { token } = useAuth();

  return useQuery({
    queryKey: ["seller", "shipment", id],
    queryFn: () => apiFetch<Shipment>(endpoints.seller.shipment(id), { token }),
    enabled: Boolean(token) && Number.isFinite(id),
  });
}

export function useDispatchShipment(id: number) {
  const { token } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: DispatchPayload) =>
      apiFetch<Shipment>(endpoints.seller.dispatchShipment(id), {
        method: "POST",
        token,
        json: payload,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ["seller", "shipment", id] });
      void queryClient.invalidateQueries({ queryKey: ["seller", "shipments"] });
    },
  });
}

export type { Paginated };

export function useSellerCommissions(filters: Filters = {}) {
  const { token } = useAuth();

  return useQuery({
    queryKey: qk.seller.commissions(filters),
    queryFn: () => getList<Commission>(endpoints.seller.commissions, token, filters),
    enabled: Boolean(token),
  });
}
