@echo off
echo Fixing npm Windows Rollup issue...
echo.

echo Step 1: Removing node_modules and package-lock.json
rmdir /s /q node_modules 2>nul
del package-lock.json 2>nul

echo Step 2: Clearing npm cache
npm cache clean --force

echo Step 3: Installing with legacy peer deps (Windows fix)
npm install --legacy-peer-deps

echo Step 4: Testing Vite
npm run dev

echo.
echo Fix completed! If this works, you can delete this file.
pause