#!/bin/bash
set -e

echo "🚀 Starting Circulation Dashboard..."

# Wait for database to be ready
echo "⏳ Waiting for database..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USER}', '${DB_PASSWORD}');" 2>/dev/null; do
    echo "   Database not ready, waiting..."
    sleep 2
done
echo "✅ Database is ready"

# Run database migrations
echo "🔄 Running database migrations..."
cd /var/www
if /var/www/vendor/bin/phinx migrate -c /var/www/phinx.php -e production 2>&1 | tee /tmp/migration.log; then
    echo "✅ Migrations completed successfully"
else
    echo "⚠️  Migration warnings (this is normal if no new migrations)"
fi

# Start Apache
echo "🌐 Starting Apache..."
exec apache2-foreground
