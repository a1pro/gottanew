# Deployment Runbook (MVP)

This document captures the minimum operational setup to keep:
- notifications sending
- Daily webhooks reliable
- transcript backfill running
- timezone handling consistent

## Required processes

### Scheduler (cron)
Run Laravel scheduler every minute:

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks defined in `routes/console.php` include notifications outboxes and Daily transcript backfill (`daily:sync-transcripts`).

### Queue worker
Run a queue worker so transcript downloads and AI tasks can run asynchronously:

```bash
php artisan queue:work --queue=default --tries=5 --timeout=60
```

If you do not run a queue worker, you can still sync transcripts using:

```bash
php artisan daily:sync-transcripts --sync
```

## Daily configuration

### Webhook endpoint
Set:
- `APP_URL` to the public backend URL
- `DAILY_WEBHOOK_URL` to `${APP_URL}/api/v1/webhooks/daily` (or your tunnel URL in local dev)

Create or update the webhook:

```bash
php artisan daily:webhooks:sync
```

If Daily returns an `hmac`, store it as `DAILY_WEBHOOK_HMAC` in the backend environment.

### Transcription storage
Daily defaults `enable_transcription_storage=false`. Set this to `true` so transcripts are persisted as a WebVTT file:

```bash
DAILY_ENABLE_TRANSCRIPTION_STORAGE=true
```

### Database timezone
To avoid environment-dependent drift with MySQL `TIMESTAMP`, set the MySQL session time zone explicitly:

```bash
DB_TIMEZONE=+00:00
```

This is applied via `config/database.php` for MySQL and MariaDB connections.

## Rollback notes
- If you need to disable transcript downloads temporarily, stop the queue worker and disable the scheduled command in `routes/console.php`.
- If you need to stop creating transcription files, set `DAILY_ENABLE_TRANSCRIPTION_STORAGE=false`.
