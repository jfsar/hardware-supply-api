---
paths:
  - 'app/Notifications/**'
  - 'config/mail.php'
---

# Notifications

## Local/dev mail is delivered through Mailpit SMTP
Mailpit is the dev SMTP catcher: containers send to `mailpit:1025`, host-run artisan serve to `127.0.0.1:1025` (published from docker-compose.yml). Inspect deliveries at http://localhost:8025. Do not use the log or array mailers outside tests.

## Auth emails are queued notification classes in app/Notifications/Auth/**
They extend Laravel's base notifications (VerifyEmail, ResetPassword) and implement ShouldQueue, so they require a running queue worker: docker compose provides one ('queue' service); on host dev run `php artisan queue:work`.

## Email verification links target the API, not an SPA
Signed links point at the named route `verification.verify` and return JSON by design — do not repoint them to a frontend URL.

## Sender identity comes from MAIL_FROM_ADDRESS / MAIL_FROM_NAME
Locally set to `no-reply@hardware-supply.test`; keep project-specific values in sync between .env and .env.example.
