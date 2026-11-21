/**
 * Tipe domain yang dipakai lintas fitur frontend.
 * Selaras dengan resource API Laravel (Phase 1–7).
 */

export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  has_more: boolean;
}

export interface Paginated<T> {
  items: T[];
  pagination: Pagination;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  children?: Category[];
}

export interface ImageSummary {
  id: number;
  url: string;
  alt_text: string | null;
}

export interface SellerSummary {
  id: number;
  store_name: string;
  slug: string;
  rating_avg?: number;
}

export interface ProductSummary {
  id: number;
  name: string;
  slug: string;
  status?: string;
  is_featured?: boolean;
  min_price: number;
  max_price: number;
  rating_avg: number;
  rating_count: number;
  sold_count: number;
  published_at: string | null;
  primary_image?: ImageSummary | null;
  category?: { id: number; name: string; slug: string };
  seller?: SellerSummary;
}

export interface InventoryInfo {
  stock: number;
  reserved_stock: number;
  available_stock: number;
  low_stock_threshold: number;
}

export interface ProductVariant {
  id: number;
  product_id: number;
  sku: string;
  variant_name: string | null;
  price: number;
  weight_gram: number | null;
  status: string;
  inventory: InventoryInfo | null;
}

export interface ProductDetail extends ProductSummary {
  seller_id: number;
  category_id: number | null;
  description: string | null;
  weight_gram: number | null;
  images?: ImageSummary[];
  variants?: ProductVariant[];
}

export interface CartItem {
  id: number;
  variant_id: number;
  quantity: number;
  product_name: string | null;
  variant_name: string | null;
  sku: string | null;
  price: number;
  line_total: number;
  weight_gram: number | null;
  available_stock: number;
  is_available: boolean;
  primary_image: string | null;
  seller: SellerSummary | null;
}

export interface CartData {
  items: CartItem[];
  summary: {
    subtotal: number;
    item_count: number;
    seller_count: number;
    has_unavailable: boolean;
  };
}

export interface ShippingOption {
  code: string;
  name: string;
  provider: string;
  cost: number;
  etd: string | null;
}

export interface CheckoutSellerGroup {
  seller: SellerSummary;
  items: {
    variant_id: number;
    product_name: string;
    variant_name: string | null;
    sku: string;
    price: number;
    quantity: number;
    line_total: number;
  }[];
  subtotal: number;
  discount: number;
  shipping_options: ShippingOption[];
  selected_service_code: string | null;
  shipping_cost: number;
  total: number;
}

export interface CheckoutSummary {
  subtotal: number;
  discount_total: number;
  shipping_total: number;
  grand_total: number;
}

export interface AvailableVoucher {
  code: string;
  name: string;
  type: "fixed" | "percent";
  scope: "platform" | "seller";
  funded_by: "platform" | "seller";
  min_purchase: number;
  discount: number;
}

export interface CheckoutPreview {
  seller_groups: CheckoutSellerGroup[];
  summary: CheckoutSummary;
  voucher: {
    code: string | null;
    name?: string | null;
    type?: string | null;
    scope?: string | null;
    funded_by?: string | null;
    discount: number;
    available: boolean;
  };
  available_vouchers: AvailableVoucher[];
  available_methods?: PaymentMethodOption[];
  expires_at: string;
}

export interface PaymentMethodOption {
  key: string;
  label: string;
  group: string;
}

export interface PaymentInstructions {
  provider: string;
  method: string | null;
  status: string;
  amount: number;
  currency: string;
  token: string | null;
  redirect_url: string | null;
  va_number: string | null;
  qr_code_url: string | null;
  qr_string: string | null;
  deeplink_url: string | null;
  biller_code: string | null;
  bill_key: string | null;
  expires_at: string | null;
  paid_at: string | null;
  available_methods?: PaymentMethodOption[];
}

export interface OrderAddress {
  type: string;
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
}

export interface InvoiceItem {
  product_name: string;
  sku: string | null;
  variant_name: string | null;
  quantity: number;
  unit_price: number;
  discount_amount: number;
  line_total: number;
}

export interface InvoiceGroup {
  seller: { id: number | null; store_name: string | null };
  seller_order_number: string;
  items: InvoiceItem[];
  subtotal: number;
  discount_total: number;
  shipping_cost: number;
  grand_total: number;
}

export interface Invoice {
  invoice_number: string;
  issued_at: string | null;
  order_number: string;
  status: string;
  currency: string;
  buyer: { name: string | null; email: string | null };
  shipping_address: OrderAddress | null;
  groups: InvoiceGroup[];
  summary: {
    subtotal: number;
    discount_total: number;
    shipping_total: number;
    grand_total: number;
  };
  payment: {
    provider: string;
    method: string | null;
    status: string;
    paid_at: string | null;
    reference: string | null;
  } | null;
}

export interface OrderItem {
  id: number;
  product_id: number | null;
  variant_id: number | null;
  product_name: string;
  sku: string;
  variant_name: string | null;
  price: number;
  quantity: number;
  discount_amount: number;
  line_total: number;
  weight_gram: number | null;
  reviewed?: boolean;
}

export interface ShipmentTimelineItem {
  status: string;
  description: string | null;
  location: string | null;
  occurred_at: string;
}

export interface Shipment {
  id: number;
  seller_order_id: number;
  provider: string;
  service_code: string | null;
  tracking_number: string | null;
  status: string;
  shipping_cost: number;
  weight_gram: number | null;
  origin_city: string | null;
  destination_city: string | null;
  dispatched_at: string | null;
  delivered_at: string | null;
  created_at: string | null;
  timeline?: ShipmentTimelineItem[];
}

export interface SellerOrder {
  id: number;
  seller_order_number: string;
  status: string;
  subtotal: number;
  discount_total: number;
  shipping_cost: number;
  grand_total: number;
  confirmed_at?: string | null;
  shipped_at?: string | null;
  delivered_at?: string | null;
  completed_at?: string | null;
  created_at?: string | null;
  seller?: SellerSummary;
  items?: OrderItem[];
  shipment?: Shipment | null;
  shipping_address?: OrderAddress | null;
}

export interface OrderSummary {
  id: number;
  order_number: string;
  status: string;
  subtotal: number;
  discount_total: number;
  shipping_total: number;
  grand_total: number;
  currency: string;
  expires_at: string | null;
  paid_at: string | null;
  created_at: string | null;
  payment?: PaymentInstructions | null;
  buyer?: { id: number; name: string | null; email: string | null } | null;
  seller_orders?: SellerOrder[];
}

export interface OrderDetail extends OrderSummary {
  cancelled_at: string | null;
  addresses?: OrderAddress[];
  items?: OrderItem[];
  status_histories?: {
    from_status: string | null;
    to_status: string;
    reason: string | null;
    created_at: string | null;
  }[];
}

export interface RegionOption {
  code: string;
  name: string;
}

export interface RegionVillageOption extends RegionOption {
  postal_code: string | null;
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

export interface SellerAddress {
  id: number;
  type: string;
  label: string | null;
  contact_name: string;
  phone: string;
  address_line: string;
  province: string;
  city: string;
  district: string | null;
  village: string | null;
  postal_code: string;
  is_default: boolean;
}

export interface SellerDocument {
  id: number;
  type: string;
  status: string;
  file_name?: string | null;
  uploaded_at?: string | null;
}

export interface SellerProfile {
  id: number;
  user_id: number;
  store_name: string;
  slug: string;
  description: string | null;
  logo_path: string | null;
  status: string;
  submitted_at: string | null;
  approved_at: string | null;
  rejected_at: string | null;
  rejection_reason: string | null;
  suspended_at: string | null;
  suspension_reason: string | null;
  rating_avg: number;
  rating_count: number;
  kyc?: { required: string[]; missing: string[]; complete: boolean };
  addresses?: SellerAddress[];
  documents?: SellerDocument[];
  owner?: { id: number; name: string; email: string; phone: string | null };
  created_at: string | null;
}

export interface SellerDashboard {
  store: SellerProfile;
  stats: {
    products: number;
    pending_orders: number;
    orders_today: number;
    revenue_this_month: number;
    low_stock_variants: number;
    wallet: { pending: number; available: number; paid: number };
  };
  stats_available: boolean;
}

export interface Wallet {
  pending_balance: number;
  available_balance: number;
  payout_reserved_balance: number;
  risk_hold_balance: number;
  debt_balance: number;
  paid_balance: number;
  currency: string;
  balance_updated_at: string | null;
}

export interface WalletTransaction {
  id: number;
  type: string;
  balance_bucket: string;
  amount: number;
  balance_before: number;
  balance_after: number;
  reference_type: string | null;
  reference_id: number | null;
  description: string | null;
  created_at: string | null;
}

export interface BankAccount {
  id: number;
  bank_name: string;
  account_number: string;
  account_name: string;
  is_default: boolean;
}

export interface Payout {
  id: number;
  payout_number: string;
  amount: number;
  status: string;
  bank: { bank_name: string; account_number: string; account_name: string };
  provider_reference: string | null;
  requested_at: string | null;
  processed_at: string | null;
  paid_at: string | null;
  rejection_reason: string | null;
}

export interface Commission {
  id: number;
  seller_order_id: number;
  seller_id: number;
  seller_order_number?: string | null;
  base_amount: number;
  rate_bp: number;
  commission_amount: number;
  rounding_adjustment: number;
  status: string;
  earned_at: string | null;
  created_at: string | null;
}

export interface Refund {
  id: number;
  refund_number: string;
  order_id: number;
  seller_order_id: number | null;
  amount: number;
  product_amount: number;
  shipping_amount: number;
  seller_debit_amount: number;
  commission_reversal_amount: number;
  status: string;
  reason: string | null;
  rejection_reason: string | null;
  provider_reference: string | null;
  provider_refunded_at: string | null;
  processed_at: string | null;
  created_at: string | null;
}

export interface Voucher {
  id: number;
  code: string;
  name: string;
  type: "fixed" | "percent";
  value: number;
  min_purchase: number;
  max_discount: number | null;
  scope: "platform" | "seller";
  seller_id: number | null;
  funded_by: "platform" | "seller";
  usage_limit: number | null;
  usage_limit_per_user: number | null;
  used_count: number;
  starts_at: string | null;
  expires_at: string | null;
  is_active: boolean;
  created_at: string | null;
}

export interface Review {
  id: number;
  order_item_id: number;
  product_id: number;
  rating: number;
  comment: string | null;
  status: string;
  user?: { name: string | null };
  published_at: string | null;
  created_at: string | null;
}

export interface AppNotification {
  id: string;
  type: string;
  title: string;
  body: string;
  data: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string | null;
}

export interface NotificationList {
  items: AppNotification[];
  unread_count: number;
  pagination: Pagination;
}

export interface AuditLog {
  id: number;
  actor_id: number | null;
  action: string;
  entity_type: string;
  entity_id: number;
  before: Record<string, unknown> | null;
  after: Record<string, unknown> | null;
  ip_address: string | null;
  request_id: string | null;
  created_at: string | null;
}

export interface AdminCategory {
  id: number;
  parent_id: number | null;
  parent_name?: string | null;
  name: string;
  slug: string;
  description: string | null;
  sort_order: number;
  is_active: boolean;
  products_count: number;
  created_at: string | null;
}

export interface AdminReview {
  id: number;
  rating: number;
  comment: string | null;
  status: string;
  product?: { id: number; name: string; slug: string };
  seller?: { id: number; store_name: string };
  user?: { id: number; name: string | null };
  published_at: string | null;
  created_at: string | null;
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  email_verified: boolean;
  status: string;
  roles: string[];
  seller?: { id: number; store_name: string; status: string } | null;
  last_login_at: string | null;
  created_at: string | null;
}
