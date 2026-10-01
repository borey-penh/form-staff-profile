# Personnel Portal — Vue 3 SPA + Laravel 12 API

A complete HR personnel system: staff self-service portal + Admin/HR portal, with a
unified approval workflow, dynamic compliances & trainings, and finance vouchers.

## Demo accounts

| Role  | Email              | Password  |
|-------|--------------------|-----------|
| Staff | staff@portal.test  | `password`|
| Admin / HR | admin@portal.test | `password` |

## Quick start

```bash
# 1. Backend (first time)
cd backend
composer install
php artisan key:generate
php artisan migrate --seed        # creates schema + demo data in MySQL `staff_profile`
php artisan storage:link

# 2. Frontend (first time)
cd ../frontend
npm install
npm run build                     # outputs to backend/public/build

# 3. Run
cd backend && php artisan serve   # http://127.0.0.1:8000
cd frontend && npm run dev        # optional: HMR dev server on http://127.0.0.1:5173

# Two ways to open the app:
#   http://127.0.0.1:8000  → Laravel serves the page (assets + HMR from :5173)
#   http://127.0.0.1:5173  → Vite serves the page directly (API proxied to :8000)
# Note: Laravel must be running either way — it owns the database and /api.
```

## Features

### Staff Portal
- **Dashboard** — profile completion, training progress, active contract, compliance
  status, recent requests, upcoming trainings, leave balances, activity feed
- **Personnel Profile** — 5-step wizard: Personal Information → Professional
  Qualifications (multi-entry education + experience) → Family (spouse, children,
  emergency contacts) → Supporting Documents (upload, HR verifies) → Declaration
  (signature pad)
- **Compliances** — dynamic list; sign each policy with name/date/signature
- **Contract History** — probationary, full-time, part-time, intermittent, volunteer,
  internship
- **Staff Online Training** — assigned courses with sectioned content, progress saving,
  auto-graded quiz, pass mark, printable certificate
- **Time Management** — Leave Application (auto day count + balances), Overtime
  (auto hours), Monthly Timesheet (full-month grid, auto totals)
- **Other Requests** — Travel Application (cost estimate), Fuel Log-Sheet (records
  table + totals), Purchase Request (item lines + totals)
- **Finance** — Payment/Expense Voucher with auto voucher number and amount lines
- **My Requests** — every submission with its full approval trail

### Admin / HR Portal
- Dashboard — headcount, active contracts, expiring contracts (60-day warning),
  pending requests by type
- Staff Management — search, add staff (default password `password`), staff detail with
  document verification (✓ Verified / ✕ Rejected) and contract creation
- Compliance Manager — **create compliance items dynamically** (no new code); activate/
  deactivate/delete; signature counts
- Training Manager — build courses (sections + quiz + pass mark), assign to all staff
- Approval Queue — Review → Approve / Reject / Return (with note) on every request type
- Finance — mark vouchers Paid
- Reports — personnel, training, leave, contract, finance summaries

### Approval workflow (unified)
Every request (Leave, Overtime, Timesheet, Travel, Fuel, Purchase, Voucher) shares one
workflow engine: `Pending → In Review → Approved / Rejected / Returned → Completed`,
with a full audit trail (`request_actions`) and status vocabulary used portal-wide.

## Architecture

```
├── backend/                        # Laravel 12 — JSON API + SPA shell
│   ├── app/
│   │   ├── Http/Controllers/Api/   # Auth, Dashboard, Profile, Compliance,
│   │   │                           # Training, Contract, Request, Admin
│   │   ├── Http/Middleware/EnsureUserIsAdmin.php
│   │   ├── Models/                 # 24 models
│   │   └── Services/RequestService.php   # unified submission + workflow engine
│   ├── database/migrations/        # 3 migration files (17 tables)
│   ├── database/seeders/PortalSeeder.php # demo users, compliances, training…
│   ├── routes/api.php
│   └── resources/views/spa.blade.php
├── frontend/
│   └── src/
│       ├── components/             # StatusBadge, SignaturePad, ToastHost
│       ├── layouts/PortalLayout.vue # sidebar + topbar shell
│       ├── router/                 # auth + role guards, lazy views
│       ├── services/               # apiClient (bearer token) + portalService
│       ├── stores/                 # Pinia: auth, toast
│       ├── utils/format.js
│       └── views/                  # auth/, profile/, compliance/, contract/,
│                                   # training/, time/, requests/, finance/,
│                                   # admin/, Dashboard, RequestIndex, NotFound
└── backend/public/build            # compiled SPA (npm run build)
```

**Auth:** Laravel Sanctum personal access tokens (bearer), stored in `localStorage`;
router guards protect `auth` and `admin` routes; 401 responses auto-redirect to login.

## API overview

All endpoints under `/api`, JSON only. See `backend/routes/api.php` for the full map.

| Area | Endpoints |
|------|-----------|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| Dashboard | `GET /dashboard` |
| Profile | `GET /profile`, `PUT /profile/personal|qualifications|family`, `POST /profile/documents`, `POST /profile/declaration` |
| Compliances | `GET /compliances`, `POST /compliances/sign` |
| Trainings | `GET /trainings`, `GET /trainings/{id}`, `POST /trainings/{id}/progress`, `POST /trainings/{id}/quiz` |
| Contracts | `GET /contracts` |
| Requests | `GET|POST /requests`, `GET /requests/{id}`, `POST /requests/{id}/act` |
| Admin | `/admin/dashboard`, `/admin/staff*`, `/admin/compliances*`, `/admin/trainings*`, `/admin/documents/{id}/verify`, `/admin/contracts`, `/admin/vouchers*`, `/admin/reports` |

## Tests

```bash
cd backend && php artisan test
```

Covers login, leave submission + admin approval + audit trail, duplicate compliance
signature rejection, overtime auto-hour calculation, and role-based access control.

## Notes

- MySQL config lives in `backend/.env` (`staff_profile` database, XAMPP defaults).
- Uploaded files and signature PNGs are stored on the `public` disk
  (`backend/public/storage`, symlinked via `php artisan storage:link`).
- The old static prototype is kept at the repo root (`index.html`, `style.css`) for
  reference only.
