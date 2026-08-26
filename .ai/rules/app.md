---
paths:
  - 'app/**'
---

# App

## All payment provider access goes through App\Contracts\PaymentGateway
Depend only on App\Contracts\PaymentGateway (+ DTOs in app/Services/Payments). PayRex SDK types must never leak past the adapter (app/Services/Payments/PayrexPaymentGateway.php). Webhook signature verification is shared via SignatureVerifier (t/te/li HMAC-SHA256 over {ts}.{raw_body} plus replay window). Never call gateway methods inside DB::transaction; use outbox rows + queued jobs. Money is integer minor units end-to-end.
