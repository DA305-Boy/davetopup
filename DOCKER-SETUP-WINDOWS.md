# Docker Desktop & WSL2 Setup (Windows)

Quick steps to install Docker Desktop and enable WSL2 (Windows 10/11). Follow these if `docker` or `docker compose` aren't available.

1) Install WSL2 (if not already)

Open an elevated PowerShell (Run as Administrator):
```powershell
wsl --install
# If your Windows doesn't support the above, follow Microsoft guide: https://docs.microsoft.com/windows/wsl/install
```

2) Install Docker Desktop

- Download: https://www.docker.com/get-started
- Run installer and follow prompts. Enable "Use the WSL 2 based engine" when prompted.
- After install, sign in if prompted and let Docker finish starting.

3) Verify Docker & Compose

Open a new PowerShell (non-elevated) and run:
```powershell
docker version
docker compose version
# or for older installs:
docker-compose --version
```

4) Common fixes
- If `docker` isn't found after install, restart your machine and VS Code.
- If `wsl --install` fails, enable features manually:
  - Enable-WindowsOptionalFeature -Online -FeatureName Microsoft-Windows-Subsystem-Linux
  - Enable-WindowsOptionalFeature -Online -FeatureName VirtualMachinePlatform

5) Run the project

From repo root:
```powershell
.\start-local.ps1
```

6) Troubleshooting
- Docker Desktop requirements: Windows 10/11 Pro, or Windows Home with WSL2. See Docker docs for details.
- If firewall blocks ports, temporarily allow Docker network or test with `curl` inside containers.

7) Quick local test checklist

- Ensure Docker Desktop is running and the whale icon shows in the system tray.
- From the repository root run (PowerShell):

```powershell
# Build and run the development stack (uses docker-compose.yml)
docker compose up --build -d

# Run migrations inside the backend container (wait until DB is ready)
docker compose exec backend php artisan migrate --force

# Optional: create admin user via tinker (inside backend container)
docker compose exec backend php artisan tinker --execute "\App\\Models\\User::create(['name'=>'Admin','email'=>'admin@example.com','password'=>Hash::make('SuperSecret123'),'is_admin'=>1]);"

# Check health endpoint
curl http://localhost:8000/api/health
```

If you see `status: ok` the backend is running. If `docker` still isn't recognized, restart Windows and ensure Docker Desktop was installed successfully.
