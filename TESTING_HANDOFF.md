# 🧪 Testing Handoff — Feature Registration & Subscription Pricing

> **Yeh file testing shuru karne se pehle kholo.** Phase one complete hai. Jo green
> hai wo neeche **COMPLETED** me hai, jo baaki hai wo **PENDING** me. Testing me jo
> error aaye usko yahin fix karte jao.

Date: 2026-10-01 · Branch: uncommitted (koi commit nahi kiya)

---

## ⛔ STOP POINT — yahan ruk gaya tha

**Phase one COMPLETE aur commit-push ho chuka hai. Agla kaam = TESTING.**

Ek saath me ye padh le, warna context waste hoga:

| Ye padh | Kyun |
| ------- | ----- |
| `TESTING_HANDOFF.md` (yehi file) | Commands, credentials, test cases, pending list |
| `docs/17-admin-panel-and-subscriptions.md` → *Feature registration* | Register karne ka permanent tareeka, Phase 2 me kaam aayega |
| `docs/00-index.md` → *Progress tracker* | COMPLETED vs PENDING ka official record |
| `docker/compose.yaml` me DB path ka comment | Wapas `database/` kar diya to bug dubara aa jayega (ADR-010) |

**Last 3 commands jo chalti hain:**

```powershell
cd D:\Amtech\amteCHAT
docker compose exec backend php artisan chat:features    # 6 features, tier, calls nahi
docker compose exec backend php artisan migrate --force   # Nothing to migrate
docker compose up -d                                      # phir http://localhost:5173
```

**If testing me error aaye:** pehle `php artisan chat:features --dry-run` chalao,
phir role matrix `tools\role-matrix.ps1` (expected: `104 passed, 0 failed`).

**Login atak jaye (`429`) — ye zaroor hoga:** console login ka limit **30 attempts
per din per IP** hai. `docker compose exec admin-backend php artisan cache:clear`
chalao. Details section 4 me.

**Credentials** (chaaron verify kiye hain):

| Role | Identifier | Password |
| ---- | ---------- | -------- |
| super_admin | `superadmin` | `super123` |
| admin | `admin` | `admin123` |
| moderator | `moderator` | `moderator123` |
| support | `support` | `support123` |

**Phase 2 ka pehla kaam (sabse bada gap):** subscription runtime enforcement —
`ChatEntitlements` abhi bhi har feature `true` deta hai, plan ka `features` array
ignore karta hai. Yaani admin me checkbox save hota hai par **user app me koi
feature lock nahi lagta**. P3 neeche.

---

## 1. Phase one kya tha (ek line me)

Feature ab **code se register hota hai** → DB me sync hota hai → subscription form
me checkbox ban jaata hai. Frontend me koi feature ka naam hardcoded nahi hai, aur
`calls` jaise fake features ab form me nahi aa sakte.

---

## 2. ✅ COMPLETED

| # | Kaam | Verify kaise |
| - | ---- | ----------- |
| 1 | `ChatFeature` enum = single registration point | Naya case add karo → `chat:features` me dikhe |
| 2 | `FeatureTier` enum: `premium` / `free` (default free) | `chat:features` output me tier column |
| 3 | `tier` column migration + 8 hardcoded features deactivate | 6 active, 8 retired |
| 4 | `FeatureCatalogueSync` — boot pe enum → `features` table | 3 boots, `updated_at` nahi chala = no churn |
| 5 | `php artisan chat:features` + `--dry-run` | Neeche commands section |
| 6 | `label()`/`blurb()` me graceful fallback | Naya case, curated label ke bina — crash nahi hua |
| 7 | Console `/admin/features` me `tier` return | 6 features, sab par tier |
| 8 | `calls` checkbox hata diya | Catalogue me absent; `calls` save → 422 |
| 9 | Validation: plan sirf **live** keyword rakh sakta hai | `calls` → 422, `not_a_feature` → 422 |
| 10 | Frontend: `PLAN_DEFAULT_FEATURES` hataya, poora DB-driven | `Subscriptions.tsx` me koi feature ka naam nahi |
| 11 | Frontend: premium = checkbox, free = read-only list | Form par dekhna |
| 12 | **Update flow** — saved rows table + Edit button | `simple/IN` row Edit dabao |
| 13 | Retired keyword row load par strip hota hai | Purani row save hoti hai, ghost keyword nahi |
| 14 | `docker/compose.yaml` DB path fix (console → live app DB) | Neeche section 5 |
| 15 | `create_admin_staff_tables` idempotent banaya | `php artisan migrate` ab fail nahi hota |
| 16 | Role matrix 104/104 | `matrix.ps1` |
| 17 | tsc + build clean | `npm run build` |

### Commands (yein yaad rakhna)

```powershell
# Feature catalogue dekhna — keyword, title, tier, naya ya purana
docker compose exec backend php artisan chat:features

# Kya badlega, bina kuch likhe
docker compose exec backend php artisan chat:features --dry-run

# Puri app DB me migrate (ab clean chalega)
docker compose exec backend php artisan migrate --force
```

### Naya feature register karna (Phase 2 me yahi use hoga)

`backend/app/Domain/Chat/Enums/ChatFeature.php` me ek case add karo. Bas.

```php
case VoiceNotes = 'voice_notes';
```

Phir:

```powershell
docker compose exec backend php artisan chat:features
```

Output me naya row `new` dikhega, admin form me checkbox aa jayega, subscription
form me tick karne se plan JSON me keyword chala jayega. **Paid** banana hai to
`ChatFeature::tier()` me us case ko `FeatureTier::Premium` return karwa do.

> Abhi sab kuch `free` hai (free-launch chal raha hai). Phase 2 me subscription
> aayega tab specific cases `Premium` mark honge.

---

## 3. 🟡 PENDING — ye bina fix kiye testing me error dega

| # | Pending | Asar | Priority |
| - | ------ | ---- | -------- |
| P1 | **`email_configs` 0 rows** | Email/SMTP page khaali. Original config lost hai, dobara daalna padega | High |
| P2 | **`payment_gateways` 0 rows** | `testpay` gateway gayab. Payment flow test nahi hoga | High |
| P3 | **Subscription runtime enforcement** | `ChatEntitlements` abhi har feature `true` deta hai, plan ka `features` read nahi karta. Checkbox save to hota hai par **user app me lock nahi laga** | High (Phase 2) |
| P4 | **Orphan DB** `backend/database/database.sqlite` | 22 users + purana data. Ab koi use nahi karta. **Merge nahi kiya** (aapke instruction). Delete bhi nahi kiya | Low — decide karna hai |
| P5 | **MySQL grant hardening** | `users` par table-level `UPDATE`, kuch redundant `INSERT`, `console_secret_change_me` hardcoded, post-migrate apply path nahi | Medium |
| P6 | **Staff audit source** | `backend/database/admin.sqlite` (3 staff) abhi bhi `Report`/`AccountAppeal`/`SupportMessage` ka `StaffUser` relation hai | Medium |
| P7 | **Sessions me city/country** | Abhi sirf IP. GeoIP DB nahi hai | Low |
| P8 | **Admin offboarding nahi hai** | Real admin account ko console se deactivate/delete nahi kar sakte. Matrix me ek probe isliye bachta hai | Medium |
| P9 | **`email_templates` 0 rows** | Templates banane padenge (UI se ban sakte hain) | Medium |
| P10 | **Login throttle daily limit** | 30 attempts/din/IP. Testing chalti rahe to lock ho jayega — `cache:clear` (section 4) | Low, but **zaroori** |

## ✅ Is push me kya verify hua

- 4on role login **alag-alag verify**: `superadmin/super123`, `admin/admin123`,
  `moderator/moderator123`, `support/support123`.
- `chat:features` output: `6 registered · 1 updated · 8 retired`.
- DB truth: `active=6`, `retired=8`, `calls active=0`.
- `tools\role-matrix.ps1` → `104 passed, 0 failed`.
- `admin/src` me **koi bhi feature ka naam nahi** (grep clean) — form poora DB se aata hai.
- Har doc me likha file path actually exist karta hai (15/15 check).
- `admin-backend/storage/framework/{cache,views}` pehle repo me **tracked** the,
  har test pe `git status` dirty hota tha. Ab ignore + `.gitignore` stubs —
  cache likhne par bhi `git status` clean rehta hai (verify kiya).
- Matrix script pehle sirf `C:\...\Temp\` me thi (restart pe gayab) — ab
  `tools\role-matrix.ps1` repo me hai.

---

## 4. Testing kaise shuru karein

```powershell
# 1. Sab up
cd D:\Amtech\amteCHAT
docker compose up -d
docker compose ps          # sab green

# 2. Catalogue dekho
docker compose exec backend php artisan chat:features

# 3. Admin console kholo -> http://localhost:5173
#    Subscriptions tab -> plan choose karo -> "Add a country" ya koi row ka "Edit"
```

### Login

| Role | Identifier | Password | Kya dekh sakta hai |
| ---- | ---------- | -------- | ----------------- |
| super_admin | `superadmin` | `super123` | Sab + Administrators + Sessions |
| admin | `admin` | `admin123` | Sab config + Team |
| moderator | `moderator` | `moderator123` | Read-only |
| support | `support` | `support123` | Sirf queues |

Chaaron ek-ek karke verify kiye hain. Ye `ConsoleStaffSeeder` ke defaults hain
(`admin-backend/database/seeders/ConsoleStaffSeeder.php:30-42`). Seeder dobara chalane
par **purana account ka password nahi badalta** — agar kisi ne login se password
change kiya hoga to yahan wala kaam nahi karega.

> ### ⚠️ Login throttle — 30 attempts / din / IP
>
> `staff-auth` limiter **per day 30** rakhta hai, per IP
> (`admin-backend/app/Providers/AppServiceProvider.php:35`). Bahut zyada baar
> login try karne se poora din console login band ho jata hai aur sab kuch
> `429` return karta hai — ye security kaam hai, error nahi.
>
> **Atak jao to:**
> ```powershell
> docker compose exec admin-backend php artisan cache:clear
> ```
>
> Matrix 4 login karta hai per run, isliye jaldi repeat mat karo — ek run me hi
> daily limit khatam ho jaata hai.

---

## 5. ⚠️ DB wiring — dhyan dene wali baat

Pichle session me **do alag SQLite files** the aur console galat waali padh raha tha:

| | File | Users | Ab |
| - | ---- | ----- | -- |
| live (app) | `backend/amtechat` | 0 | ✅ app isi par chal raha hai |
| orphan | `backend/database/database.sqlite` | 22 | ❌ console ab use nahi karta |

Isiliye pichli baar ki saari cleanup (probe templates, gateway, `features=["probe"]`)
**khali live DB** par lagi thi — console wali file me sab bacha hua tha.

**Fix:** sirf `docker/compose.yaml` me console ka path badla. **Backend ko haath nahi
lagaya** — user side bilkul untouched. Koi merge nahi kiya.

```yaml
- ../backend:/srv/app-database          # pehle: ../backend/database
- APP_DB_DATABASE=/srv/app-database/amtechat   # pehle: .../database.sqlite
```

> Is path ko wapas `database/` par mat le jao. Comment `docker/compose.yaml` me
> wajah ke saath likha hai.

**Consequence:** `email_configs`, `payment_gateways`, `plan_prices` jo orphan me
the ab yahan nahi hain → P1, P2, P9. `plan_prices` me `simple/IN` price 999
(features `[]`) bacha hai.

---

## 6. Testing me error aaye to

1. Feature catalogue se related → `php artisan chat:features --dry-run` chalao
2. Plan save 422 de → error body me `field` dekho (`features.0` = keyword invalid)
3. DB hi galat lag raha hai → `docker compose exec admin-backend php -r "..."`
   se `APP_DB_DATABASE` confirm karo, `backend/amtechat` honi chahiye
4. Role se related → `tools\role-matrix.ps1` (`104 passed, 0 failed` expected).
   Usme 4on role ke dev passwords already hain (wahi jo `ConsoleStaffSeeder`
   me public hain — ise secret mat samjho). Ye script repo me hai, temp me nahi,
   isliye restart ke baad bhi milega.

---

## 7. Test script (Phase one ke liye)

| # | Test | Expected |
| - | ---- | -------- |
| T1 | `chat:features` | 6 rows, `calls` nahi, sab par tier |
| T2 | `chat:features` do baar | Beech me koi "Updated"/"Retired" nahi |
| T3 | Subscriptions → paid feature tick → Save | "Pricing saved" |
| T4 | Saved row ka Edit dabao | Form me values aa jayein, button "Update pricing" |
| T5 | Price badal kar Update | Row update, list me nayi value |
| T6 | `calls` ya unknown keyword save (API se) | 422 |
| T7 | Free features wala section | Tick hone ki box nahi, "Free for everyone" |
| T8 | support/moderator Subscriptions khole | Checkbox disabled, "View only" |
| T9 | Naya case add → `chat:features` | Naya row, form me checkbox |
| T10 | support login → `/admin/features` | 403 |

---

## 8. Phase 2 me kya hoga

- Subscription runtime enforcement (P3) — plan ke `features` user app me lock lagayenge
- `tier()` me specific cases `Premium` mark
- User app me price cards (Basic/Standard/Premium) — doc 17 me design hai
- MySQL grant hardening (P5)
- Staff audit source decide (P6)
