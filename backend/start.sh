#!/bin/sh
set -e

# Start script for container: generate key, migrate, then serve

echo "Starting container entrypoint..."

if [ -f artisan ]; then
  # Ensure APP_KEY
  if [ -z "${APP_KEY}" ]; then
    echo "Generating APP_KEY"
    php artisan key:generate --force
  fi

  # Run migrations with retry (wait for DB readiness)
  echo "Waiting for DB and running migrations with retries"
  MAX_ATTEMPTS=30
  ATTEMPT=0
  until php artisan migrate --force
  do
    ATTEMPT=$((ATTEMPT+1))
    if [ "$ATTEMPT" -ge "$MAX_ATTEMPTS" ]; then
      echo "Migrations failed after $ATTEMPT attempts"
      break
    fi
    echo "Migration attempt $ATTEMPT failed; retrying in 3s..."
    sleep 3
  done

  # Start the Laravel dev server
  PORT_TO_USE=${PORT:-8000}
  echo "Starting Laravel dev server on 0.0.0.0:${PORT_TO_USE}"
  php artisan serve --host=0.0.0.0 --port=${PORT_TO_USE}
else
  echo "artisan not found. Are you in the correct working directory?"
  exec "$@"
fi
