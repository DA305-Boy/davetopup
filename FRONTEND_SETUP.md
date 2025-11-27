# Frontend Setup Guide — Node.js Required

**Your npm error:** Node.js is not installed or not in PATH.

## Quick Fix (Windows)

1. Download Node.js LTS from https://nodejs.org/
2. Run the installer (use default paths)
3. Close and reopen PowerShell
4. Verify:
```powershell
node -v
npm -v
```

Then run:
```powershell
cd "C:\Users\dawin\Documents\dave top up\frontend"
npm install
npm run dev
```

## If npm install still fails

Clear npm cache and reinstall:
```powershell
npm cache clean --force
npm install
```

If you see TypeScript errors in the editor, restart VS Code's TypeScript server (Ctrl+Shift+P → "TypeScript: Restart TS Server").

---

Backend development can proceed independently. Once Node is installed and `npm run dev` runs successfully, you can test the full stack.
