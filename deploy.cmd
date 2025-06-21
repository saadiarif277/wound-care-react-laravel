@if "%SCM_TRACE_LEVEL%" NEQ "4" @echo off

:: ----------------------
:: KUDU Deployment Script
:: Version: 1.0.17
:: ----------------------

:: Prerequisites
:: -------------

:: Verify node.js installed
where node 2>nul >nul
IF %ERRORLEVEL% NEQ 0 (
  echo Missing node.js executable, please install node.js, if already installed make sure it can be reached from current environment.
  goto error
)

:: Setup
:: -----

setlocal enabledelayedexpansion

SET ARTIFACTS=%~dp0%..\artifacts

IF NOT DEFINED DEPLOYMENT_SOURCE (
  SET DEPLOYMENT_SOURCE=%~dp0%.
)

IF NOT DEFINED DEPLOYMENT_TARGET (
  SET DEPLOYMENT_TARGET=%ARTIFACTS%\wwwroot
)

IF NOT DEFINED NEXT_MANIFEST_PATH (
  SET NEXT_MANIFEST_PATH=%ARTIFACTS%\manifest

  IF NOT DEFINED PREVIOUS_MANIFEST_PATH (
    SET PREVIOUS_MANIFEST_PATH=%ARTIFACTS%\manifest
  )
)

IF NOT DEFINED KUDU_SYNC_CMD (
  :: Install kudu sync
  echo Installing Kudu Sync
  call npm install kudusync -g --silent
  IF !ERRORLEVEL! NEQ 0 goto error

  :: Locally just running "kuduSync" would also work
  SET KUDU_SYNC_CMD=%appdata%\npm\kuduSync.cmd
)

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
:: Deployment
:: ----------

echo Handling Laravel deployment.

:: 1. KuduSync
IF /I "%IN_PLACE_DEPLOYMENT%" NEQ "1" (
  call :ExecuteCmd "%KUDU_SYNC_CMD%" -v 50 -f "%DEPLOYMENT_SOURCE%" -t "%DEPLOYMENT_TARGET%" -n "%NEXT_MANIFEST_PATH%" -p "%PREVIOUS_MANIFEST_PATH%" -i ".git;.hg;.deployment;deploy.cmd"
  IF !ERRORLEVEL! NEQ 0 goto error
)

:: 2. Install Composer dependencies
IF EXIST "%DEPLOYMENT_TARGET%\composer.json" (
  pushd "%DEPLOYMENT_TARGET%"
  echo Installing Composer dependencies...
  call php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  call php composer-setup.php --install-dir=. --filename=composer.phar
  call php -r "unlink('composer-setup.php');"
  call php composer.phar install --no-dev --optimize-autoloader --no-interaction
  IF !ERRORLEVEL! NEQ 0 goto error
  popd
)

:: 3. Install npm packages and build
IF EXIST "%DEPLOYMENT_TARGET%\package.json" (
  pushd "%DEPLOYMENT_TARGET%"
  echo Installing npm packages...
  call npm install --production
  IF !ERRORLEVEL! NEQ 0 goto error

  echo Building assets...
  call npm run build
  IF !ERRORLEVEL! NEQ 0 goto error
  popd
)

:: 4. Laravel specific tasks
pushd "%DEPLOYMENT_TARGET%"

:: Copy .env.example to .env if .env doesn't exist
IF NOT EXIST ".env" (
  IF EXIST ".env.example" (
    echo Creating .env file from .env.example
    copy .env.example .env
  )
)

:: Generate application key if needed
echo Generating application key...
call php artisan key:generate --force
IF !ERRORLEVEL! NEQ 0 goto error

:: Clear and cache config
echo Optimizing Laravel...
call php artisan config:clear
call php artisan config:cache
call php artisan route:clear
call php artisan route:cache
call php artisan view:clear
call php artisan view:cache

:: Run migrations (optional - uncomment if needed)
:: echo Running database migrations...
:: call php artisan migrate --force
:: IF !ERRORLEVEL! NEQ 0 goto error

popd

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
goto end

:: Execute command routine that will echo out when error
:ExecuteCmd
setlocal
set _CMD_=%*
call %_CMD_%
if "%ERRORLEVEL%" NEQ "0" echo Failed exitCode=%ERRORLEVEL%, command=%_CMD_%
exit /b %ERRORLEVEL%

:error
endlocal
echo An error has occurred during web site deployment.
call :exitSetErrorLevel
call :exitFromFunction 2>nul

:exitSetErrorLevel
exit /b 1

:exitFromFunction
()

:end
endlocal
echo Finished successfully.
