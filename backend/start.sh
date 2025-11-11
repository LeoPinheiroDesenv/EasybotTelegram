#!/bin/sh
set -e

echo "Waiting for PostgreSQL to be ready..."
sh ./wait-for-postgres.sh postgres echo "PostgreSQL is ready!"

echo "Running migrations..."
npm run migrate

echo "Starting server..."
npm run dev

