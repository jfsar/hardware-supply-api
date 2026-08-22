---
paths:
  - docker-compose.yml
---

# General

## Run artisan serve with --no-reload in Docker and restart containers after .env changes
Laravel's serve command strips every env var not whitelisted (ServeCommand::$passthroughVariables) from spawned php -S workers unless --no-reload is used — compose-provided DB_*/MAIL_*/QUEUE_* vars never reach web requests, which then silently fall back to the host .env (e.g. sqlite). The app service therefore runs `php artisan serve ... --no-reload`. queue:work/schedule:work cache config at boot, so restart all containers (docker compose up -d --force-recreate or docker compose restart <svc>) after changing .env values that are not set by compose environment.
