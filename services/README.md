# services/ — Future Microservices

v1 ships as a **modular monolith** (ADR-001): the whole domain lives in the
Laravel `backend/` app behind a typed, contract-based layer
(`App\Domain\*\Contracts`). This folder is reserved for the hot paths that
Phase 3 extracts when load demands (docs/02 roadmap + docs/03 architecture).

## Extraction candidates

| Candidate service | Why extract | First extraction trigger |
| ----------------- | ----------- | ------------------------ |
| Chat (Reverb + Redis) | High write throughput, WS scaling | > 5k concurrent WS connections |
| Media / Transcode | CPU-heavy, isolate failures | Transcode queue > 10k jobs/day |
| Feed fan-out | High read amplification | Feed p95 > 200ms |
| Notifications | Back-pressure isolation | Notifications queue backlog |

## Rule of thumb

- Keep extracting **deliberately**, never preemptively (YAGNI; ADR-001).
- A service only moves here once its Laravel contract has a stable shape and
  a test suite that mirrors it (contract tests = the seam).
- Each `services/<name>/` lands with its own Dockerfile + README + CI test
  job when extraction actually starts.