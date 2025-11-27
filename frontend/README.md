# DaveTopUp Frontend (Dev)

This is a minimal React + TypeScript front-end scaffold for the checkout component.

Quick start (Windows PowerShell):

```powershell
cd "c:\Users\dawin\Documents\dave top up\frontend"
npm install
npm run dev
```

Environment variables (development):
- Create a `.env` file in `frontend/` if needed with `VITE_STRIPE_KEY` and `VITE_API_BASE`.

Notes:
- The checkout component expects `process.env.REACT_APP_API_BASE` in the original code; in this Vite scaffold it will be `import.meta.env.VITE_API_BASE` — update the component or set compatibility shims.
- Next step: confirm auth approach (Laravel Sanctum recommended). Backend changes required for Sanctum will be added after confirmation.
