---
paths:
  - 'app/Actions/Catalog/**'
---

# Catalog

## Media goes to the r2 disk with signed URLs; default disk stays local
Catalog media (images/documents) stores on the `r2` disk — config('catalog.media_disk') — using S3-compatible Cloudflare R2 credentials from AWS_* env vars. Rows persist storage_disk+path; URLs render via App\Support\MediaUrl::for() which uses temporaryUrl (signed, catalog.signed_url_minutes) on s3-driver disks and falls back to url(). Never switch FILESYSTEM_DISK to r2: account exports are private and must stay on the local default disk.
