#!/bin/bash
# Export existing database from Synology for Docker migration

echo "Exporting circulation_dashboard database from Synology..."

# Create db_init directory for Docker entrypoint
mkdir -p db_init

# Export database from Synology
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 \
  "mysqldump -ucirc_dash -p'Barnaby358@Jones!' \
   --socket=/run/mysqld/mysqld10.sock \
   circulation_dashboard \
   --single-transaction \
   --routines \
   --triggers \
   --events" > db_init/01_initial_data.sql

if [ $? -eq 0 ]; then
    echo "✓ Database exported successfully to db_init/01_initial_data.sql"
    echo "  Size: $(du -h db_init/01_initial_data.sql | cut -f1)"
else
    echo "✗ Database export failed"
    exit 1
fi
