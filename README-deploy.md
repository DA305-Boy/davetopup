# Deploy Guide — Backend (Laravel) + Frontend (Vercel)

This document shows how to deploy the Laravel backend using Docker (Render or Fly.io) and the frontend to Vercel. It includes local test commands, Render YAML and a simple Procfile.

---

Quick summary (Haitian Creole / English):
- Frontend → Vercel
- Backend → Render (or Fly.io)

---

1) Build & test backend locally with Docker

Prereq: Docker installed.

Build the backend image (from repository root):
```powershell
cd "C:\Users\dawin\Documents\dave top up"
docker build -t davetopup-backend -f backend/Dockerfile .
```

Run container (map port 8000):
```powershell
docker run --rm -it -p 8000:8000 \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=host.docker.internal \
  -e DB_PORT=3306 \
  -e DB_DATABASE=davetopup \
  -e DB_USERNAME=root \
  -e DB_PASSWORD=secret \
  davetopup-backend
```

Notes:
- The `start.sh` script will run `php artisan migrate --force` and start the built-in server on `$PORT` (default 8000).
- For production you should use `php-fpm` + `nginx` or a managed PHP runtime. This Dockerfile is a pragmatic, easy-to-deploy image for Render/Fly.io.

2) Deploy backend to Render (Docker)

- Push your repo to GitHub.
- In Render dashboard, create a new Web Service → Connect GitHub repository.
- Choose **Environment: Docker** and set `Dockerfile path` to `backend/Dockerfile` (or use `render.yaml` placed at repo root).
- Set environment variables in Render (Dashboard → Environment):
  - `APP_KEY` (you can run `php artisan key:generate` locally and add it), or allow `start.sh` to generate it.
  - Database variables: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - Payment keys: `STRIPE_SECRET`, `STRIPE_PUBLIC`, etc.

 Run migrations (Render automated using `render.yaml`)
 
 This repository includes a `render.yaml` manifest that declares a web service and a managed MySQL database. The manifest contains `deployCommands` which will be executed on each successful deploy:
 
 - `php artisan migrate --force`
 - `php artisan cache:clear`
 - `php artisan config:cache`
 
 When you connect the GitHub repo to Render and enable `autoDeploy`, Render will:
 1. Build and push the Docker image from `backend/Dockerfile.prod`.
 2. Provision the managed MySQL database (`davetopup-mysql`).
 3. Create the service and inject database credentials in the Render dashboard.
 4. Run the `deployCommands` during deploy — this runs migrations automatically.
 
 Important: After Render provisions the DB, open the Render dashboard for the Web Service and set the following env vars using the values from the managed database (or set them in the `render.yaml` before connecting):
 
 - `DB_HOST` — the managed DB host
 - `DB_PORT` — typically `3306`
 - `DB_DATABASE` — `davetopup`
 - `DB_USERNAME` — the DB user
 - `DB_PASSWORD` — the DB password
 
Additionally, set these application secrets in Render's Environment variables (important):

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` (generate locally with `php artisan key:generate` and paste it, or let `start.sh` create one at first boot)
- `OPENAI_API_KEY` — required if you enable the server-side chatbot (`/api/chat/respond`). Keep this secret; do NOT commit it to the repository.
- `OPENAI_MODEL` — optional (e.g. `gpt-4`), defaults to `gpt-4` if unset.
- Payment-related secrets: `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `PAYPAL_CLIENT_ID`, `PAYPAL_SECRET`, etc.

If you prefer to control migration timing manually, remove `php artisan migrate --force` from `deployCommands` and run migrations via the Render Shell after the service is live.

3) Deploy frontend to Vercel

- In Vercel, create a new Project → Import Git Repository → select the repo.
- Set Project Root to `frontend`.
- Build settings:
  - Framework Preset: `Vite`
  - Install Command: `npm install`
  - Build Command: `npm run build`
  - Output Directory: `dist`
- Add Environment Variables in Vercel → `VITE_API_URL` = `https://api.your-backend-domain.com`

4) Fly.io alternative (Docker)

- Install Fly CLI: https://fly.io/docs/getting-started/installing-flyctl/
- `fly launch` in repo root (choose app name and region). Use `backend/Dockerfile` as Dockerfile.
- Set secrets with `fly secrets set DB_PASSWORD=...` etc.
- `fly deploy`

5) Create admin user (one-time)

After backend up & migrated, create an admin user:

Option A — using tinker (recommended):
```powershell
# SSH into server or run in container
php artisan tinker
>>> \App\Models\User::create(['name'=>'Admin','email'=>'admin@davetopup.com','password'=>Hash::make('SuperSecret123'),'is_admin'=>true]);
```

Option B — insert directly into DB.

6) Test admin create seller flow

Get admin token (login):
```bash
curl -X POST https://api.your-backend-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@davetopup.com","password":"SuperSecret123"}'
```

Call create-seller endpoint (replace {ADMIN_TOKEN}):
```bash
curl -X POST https://api.your-backend-domain.com/api/admin/stores \
  -H "Authorization: Bearer {ADMIN_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"name":"Seller Name","email":"seller@example.com","password":"SellerPass123","store_name":"Seller Store"}'
```

7) Local dev notes

- If you want a production-grade stack, create a separate `nginx` image and run `php-fpm` in the app container, or use a managed service (Render buildpacks can also run Laravel without Docker).
- Add healthcheck endpoints (e.g. `/api/health`) so the platform can monitor app readiness.

---

If you want, I can now:
- (A) Add a `docker-compose.yml` that spins up the backend container + a local MySQL for development/testing.
- (B) Replace the simple `artisan serve` approach with `php-fpm` + `nginx` multi-stage Dockerfile for production.
- (C) Prepare a `render.yaml` with database service + web service full configuration.

Tell me which option you prefer and I will add it.
