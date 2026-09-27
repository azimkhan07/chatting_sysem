# 08 — Media Pipeline (posts & reels)

Goal: instant-feeling uploads, professional output, never block the request thread.

## Status — read this first

This page mixes **what v1 actually does** with the **target design**. v1 is deliberately
much smaller than the target: uploads are validated and stored synchronously on the
request thread, and there is no transcode queue, no HLS, no poster/thumbnail generation
and no EXIF rewriting yet.

| Capability | v1 (shipped) | Target (not built) |
| ---------- | ------------- | ------------------ |
| Validation | Content sniffed in PHP (`App\Support\Media\MediaInspector`): magic bytes decide image vs video, real MIME recorded, extension normalised to match the bytes | same + `ffprobe` for duration/codec probing |
| Limits | image ≤ 8 MB, video ≤ 100 MB, ≤ 5 media per post, avatar/cover ≤ 5 MB and 64–6000 px | same + reels duration/aspect enforcement |
| Storage | Local `public` disk, original file preserved, root-relative `/storage/...` URLs (`ASSET_URL` can point at a CDN) | S3-compatible `media` disk, signed URLs for DM attachments |
| Delivery | Single progressive file played natively by `<video>` (MP4/MOV/WebM) | Adaptive HLS renditions + posters + thumbnails |
| Re-scanning | None — bytes are stored as uploaded, so EXIF/GPS is **not** stripped yet | Strip EXIF, re-orient, generate thumb/card/full sizes |
| Queue | None; the request does inspect + store inline | `media` queue: inspect → transcode → thumbnails/poster → Ready event |
| Upload sessions | Single-request multipart upload | Chunked resumable `UploadSession` + presigned PUT |
| Moderation | None | Hash duplicate checks, moderation queue |

Everything in the "Target" column belongs to Phase 2/3 work and should not be quoted as
current behaviour. `ffmpeg`/`ffprobe`, `config/media.php` and `/health/media` below are
design notes only — none of them exist in the codebase today.

## Stages (target design)

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

- **Magic-byte + MIME whitelist** server-side (jpg/png/webp/gif; mp4/webm + mov) — shipped in
  `MediaInspector`. The sniffed type wins over the client-declared one, so a video named
  `.png` is stored as `.mp4` rather than rejected.
- Limits: image ≤ 8 MB; video ≤ 100 MB; ≤ 5 media files per post. Reel duration/aspect
  enforcement is not implemented yet.
- **Chunked upload** resumes; idempotent (`UploadSession`) — target, not shipped.
- Transcodes run on `media` queue with high concurrency control; failures surface as
  `status: failed` + retry job with cap. — target, not shipped.
- Object store: S3-compatible disk `media`. Local dev: `storage/app/media`. — target; v1 uses
  the default `public` disk.
- Expiring/signed URLs for DM attachments; eager-load paths via `post_media`. — target.
- EXIF/GPS stripped server-side at ingest (privacy). — target; **open gap in v1**, since the
  uploaded bytes are stored untouched.

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