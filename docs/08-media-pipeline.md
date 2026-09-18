# 08 — Media Pipeline (posts & reels)

Goal: instant-feeling uploads, professional output, never block the request thread.

## Stages

```
  POST /uploads (session)          ──▶ presign destination + upload token
  client streams (chunked PUT)     ──▶ object stores the raw file
  POST /posts | /reels (token)     ──▶ row created (status=pending)
                                       queue:media job
  queue worker:
    1 inspect (ffprobe)            2 transcode (h264/AAC) to adaptives
    3 thumbnails/poster            4 strip EXIF, orient
    5 move to final keys          6 mark Ready (+ event → broadcast/post on feed)
  client polls GET /posts/{id}     → status:ready with playable urls
```

## Formats

| Type   | Deliverables |
| ------ | ------------ |
| Image  | original (web-optimized) + 3 sizes (thumb 160px, card 640px, full 1280px), WebP/AVIF headers |
| Video  | HLS master + adaptive renditions (720p/1080p), poster, duration_ms, dims |

## Rules

- **Magic-byte + MIME whitelist** server-side (jpg/png/webp/gif; mp4/webm + mov).
- Limits: image ≤ 8 MB; video ≤ 200 MB (v1); reels ≤ 90 s vertical-ish; aspect enforced
  softly (portrait > square for reels tab).
- **Chunked upload** resumes; idempotent (`UploadSession`). 
- Transcodes run on `media` queue with high concurrency control; failures surface as
  `status: failed` + retry job with cap.
- Object store: S3-compatible disk `media`. Local dev: `storage/app/media`.
- Expiring/signed URLs for DM attachments; eager-load paths via `post_media`.
- EXIF/GPS stripped server-side at ingest (privacy).

## Views/analytics (reels)

- Play **views** ticked via Reverb event → Redis counter → flushed to DB periodically
  (batched job). `shares_count`, `likes_count` are DB counters updated in the same
  business transaction as the domain event (strong consistency for user-visible count).

## Poster + HLS generation tooling

- `ffmpeg` + `ffprobe` binary config-driven (`config/media.php`).
- Queue workers tagged `media` must have these binaries; Docker image includes them.
- Healthcheck endpoint `/health/media` returns ffmpeg availability + queue depth.

## Content safety (v1 baseline)

- Upload bucket checks: dimensions, duration, obvious duplicates (hash) → flag for review.
- Offensive/fraud detection is a manual moderation queue in v1 (docs suffice);
  automated scanning is v2 (Google Cloud Vision / custom models).
- DM attachments scanned too (hash-based blocklist).

## Performance budget

- Request path never sync-transcodes. Upload ack < 500 ms; readiness poll ≤ 90 s p95.
- Thumbnails eligible for CDN cache with long TTL; HLS segments CDN-cacheable.