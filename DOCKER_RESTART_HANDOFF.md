# 🐳 Docker Restart Handoff — amteCHAT (10-days-live prep)

> **Yeh file restart ke baad kholo.** Sab kuch exact yahan likha hai — koi confusion nahi, koi command bhoolte nahi.

---

## 1. Restart ke BAAD — Docker Desktop settings (sirf ek baar)

1. **Docker Desktop** kholo
2. **Settings (gear)** → **General**
   - `Use the WSL 2 based engine` → ✅ **ON** (yeh engine hi WSL2 chala raha tha — isi liye error aa raha tha)
   - `Expose daemon on tcp://localhost:2375` → ❌ **OFF** (security — koi zaroorat nahi)
3. **Settings → Resources → WSL Integration**
   - `Enable integration with my default WSL distro` → ✅ **ON**
   - Ubuntu **ON** hoona chahiye (tumhari installed WSL distro)
4. `Apply & Restart` → Docker Desktop **restart** ho jayega (engine green hone tak 1-2 min)

---

## 2. Confirm — Docker engine ready (green)

**Docker Desktop** opar right me whale 🐳 icon → **RUNNING** (green). Ya terminal me:

```powershell
docker --version
```

`Docker version 29.x, build ...` dikhe to ✅ ready.

---

## 3. 3 CMD — konse folder me terminal kholoni hai

> **Folder:** `D:\Amtech\amteCHAT` (root — jahan `docker-compose.yml` hai)
>
> ⚠️ **Terminal wahi kholo jahan docker-compose.yml hai** — `docker` folder me nahi jana (wahan files chala ke build context toot jayega).

**CMD 1 — validate compose (pehle, bina run kiye check):**
```powershell
cd D:\Amtech\amteCHAT
docker compose config --quiet
```
> ✅ `kuch nahi likha = compose SYNTAX SAIHI` (command code 0). Agar error aaye to paste karo.

**CMD 2 — build + start (sab services up ho jayengi):**
```powershell
docker compose up -d --build
```
> Pehli baar 3-8 min (images build hogi). End me likhega: `Creating redis`, `Creating backend`, `Creating queue`, `Creating scheduler`, `Creating mysql` → `Started`.

**CMD 3 — healthcheck (sab green hona zaroori):**
```powershell
docker compose ps
```
> Dekhna chahiye:
> - `mysql` → `healthy`
> - `redis` → `healthy`
> - `backend` → `Up`
> - `queue` → `Up`
> - `scheduler` → `Up`
>
> ⏳ Agar `mysql`/`redis` **Starting** me 10s se zyada atke → 15 sec wait karke `docker compose ps` dobara. MySQL pehli baar initial setup karta hai.

---

## 4. Backend — Redis se queue/eager-loading enable

> ✅ **Predis already installed hai** (vendor me, composer.lock me v3.6.1) — tumne khud `composer require predis/predis` chala chukke ho.
> ✅ `.env` me `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST/PORT/PASSWORD` pehle se set.

Backend container ke andar `php artisan` chalake verify:

```powershell
docker compose exec backend php artisan cache:clear
docker compose exec backend php artisan queue:restart
docker compose exec backend php artisan test --compact
```
> **Last line**: `Tests: ... 100 passed` → **sab green** ✅

---

## 5. Laravel app (browser) + sure check

App frontend `npm run dev` se change karbhav hoga, API backend Docker me chal rahi hai:
- API base URL **backend Docker** par: `http://localhost:8000` (compose me port map kiya)
- Frontend local par: `http://localhost:5173`

**Quick smoke (bina browser):**
```powershell
docker compose exec backend php artisan route:list --path=/api/v1 --columns=uri,method | Select-Object -First 20
```

---

## 6. Git — release lock (yahan se AGLA step)

- **`docker-compose.yml` root par rehne do** — `docker/` folder me MOVE mat karna (build-context `context: .` root-relative hai, move karne se `backend/Dockerfile` nahi milega → build fail)
- Backend `Dockerfile` bhi `backend/` me hi (root-relative path ke liye)
- Sab committed + pushed already (back-end FPM image + compose + predis in lock). Koi move/rename nahi.

---

### ✅ Final green-state (restart se pehle ka truth):
- Backend: **100 tests / 366 assertions** ✅
- Frontend: **build green** (513 modules, 3.55s) ✅
- Git: **0 uncommitted, 0 conflicts**, sab `main -> main` pushed ✅
- Roadmap infra (Docker multi-service + Redis eager/queue/scheduler): **files ready, ab bas `docker compose up` run karna baki** — jo upar section 3 me hai 🚀
