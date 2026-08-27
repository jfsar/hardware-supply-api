---
paths:
  - 'app/Actions/Checkout/**,app/Services/Pricing/**'
---

# Pricing

## Shipping context travels as an array, not DTOs
Checkout actions (ValidateCheckout/PlaceOrder) build a nullable `$shippingContext` array (destination geo ids + method_code + pickup_location_id) and pass it to CartTotalsCalculator::calculate(). Null keeps legacy zero-cost preview totals; when a method is provided, the calculator constructs the ShippingQuoteRequest/Result DTOs internally. Pickup bypasses zone/rate lookup so geo ids can default to 0.
