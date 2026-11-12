<?php

namespace App\Support\Exceptions;

/**
 * Kode error aplikasi yang dikirim ke klien pada field `errors.code`.
 * Nilai stabil; jangan diubah tanpa alasan yang jelas.
 */
enum ErrorCode: string
{
    case AuthTokenMissing = 'AUTH_TOKEN_MISSING';
    case AuthTokenInvalid = 'AUTH_TOKEN_INVALID';
    case AuthTokenExpired = 'AUTH_TOKEN_EXPIRED';
    case AuthSessionRevoked = 'AUTH_SESSION_REVOKED';
    case AuthAccountDisabled = 'AUTH_ACCOUNT_DISABLED';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case ValidationFailed = 'VALIDATION_FAILED';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case EmailAlreadyLinked = 'EMAIL_ALREADY_LINKED';
    case EmailNotVerified = 'EMAIL_NOT_VERIFIED';
    case SellerAlreadyExists = 'SELLER_ALREADY_EXISTS';
    case SellerNotFound = 'SELLER_NOT_FOUND';
    case SellerNotActive = 'SELLER_NOT_ACTIVE';
    case DocumentAlreadyUploaded = 'DOCUMENT_ALREADY_UPLOADED';
    case ProductNotPublishable = 'PRODUCT_NOT_PUBLISHABLE';
    case SkuAlreadyExists = 'SKU_ALREADY_EXISTS';
    case InventoryInvalid = 'INVENTORY_INVALID';
    case ImageLimitReached = 'IMAGE_LIMIT_REACHED';
    case CartEmpty = 'CART_EMPTY';
    case ProductUnavailable = 'PRODUCT_UNAVAILABLE';
    case InsufficientStock = 'INSUFFICIENT_STOCK';
    case IdempotencyKeyRequired = 'IDEMPOTENCY_KEY_REQUIRED';
    case IdempotencyConflict = 'IDEMPOTENCY_CONFLICT';
    case OrderNotCancellable = 'ORDER_NOT_CANCELLABLE';
    case InvoiceNotAvailable = 'INVOICE_NOT_AVAILABLE';
    case VoucherNotAvailable = 'VOUCHER_NOT_AVAILABLE';
    case PaymentNotFound = 'PAYMENT_NOT_FOUND';
    case PaymentAlreadyPaid = 'PAYMENT_ALREADY_PAID';
    case PaymentNotRetryable = 'PAYMENT_NOT_RETRYABLE';
    case PaymentMethodInvalid = 'PAYMENT_METHOD_INVALID';
    case PaymentAmountMismatch = 'PAYMENT_AMOUNT_MISMATCH';
    case PaymentGatewayError = 'PAYMENT_GATEWAY_ERROR';
    case WebhookSignatureInvalid = 'WEBHOOK_SIGNATURE_INVALID';
    case WebhookProviderUnknown = 'WEBHOOK_PROVIDER_UNKNOWN';
    case ShipmentNotFound = 'SHIPMENT_NOT_FOUND';
    case ShipmentAlreadyDispatched = 'SHIPMENT_ALREADY_DISPATCHED';
    case ShipmentNotDispatchable = 'SHIPMENT_NOT_DISPATCHABLE';
    case TrackingNumberTaken = 'TRACKING_NUMBER_TAKEN';
    case OrderNotConfirmable = 'ORDER_NOT_CONFIRMABLE';
    case ShippingQuoteUnavailable = 'SHIPPING_QUOTE_UNAVAILABLE';
    case RefundNotProcessable = 'REFUND_NOT_PROCESSABLE';
    case RefundAmountInvalid = 'REFUND_AMOUNT_INVALID';
    case InsufficientBalance = 'INSUFFICIENT_BALANCE';
    case PayoutInProgress = 'PAYOUT_IN_PROGRESS';
    case PayoutNotProcessable = 'PAYOUT_NOT_PROCESSABLE';
    case PayoutNotCancellable = 'PAYOUT_NOT_CANCELLABLE';
    case BankAccountRequired = 'BANK_ACCOUNT_REQUIRED';
    case WalletMismatch = 'WALLET_MISMATCH';
    case StateTransitionInvalid = 'STATE_TRANSITION_INVALID';
    case ServerError = 'SERVER_ERROR';
}
