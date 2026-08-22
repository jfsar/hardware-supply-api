---
paths:
  - 'app/Services/**'
---

# Services

## Invokable services; security outcomes recorded via RecordSecurityEvent
Services are single-purpose invokable classes (e.g. RecordSecurityEvent) or stateless utility services (e.g. Totp). Record every security-relevant outcome (login_failed, login_success, session_revoked, ...) through RecordSecurityEvent so IP, user agent, and request_id context are captured consistently — never insert SecurityEvent rows ad hoc.
