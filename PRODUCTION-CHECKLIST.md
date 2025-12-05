# ⚠️ PRODUCTION OPERATIONS CHECKLIST

**Before doing ANYTHING on production, Claude MUST complete this checklist.**

---

## 📋 PRE-FLIGHT CHECK (MANDATORY)

Before ANY production operation, verify:

- [ ] I have read `.claude/CLAUDE.md`
- [ ] I have read `docker-compose.yml`
- [ ] I know the exact connection details (see below)
- [ ] I have a written plan with max 3 steps
- [ ] I am NOT guessing or trying random approaches

**If I cannot check all boxes above: STOP and read documentation.**

---

## 🔌 CONNECTION DETAILS (Copy/Paste Ready)

### Database Connection (from web container)

**When executing PHP scripts via web container:**
```php
$host = 'database';  // Docker Compose service name
$port = 3306;
$username = 'root';
$password = 'RootPassword456!';
$dbname = 'circulation_dashboard';

// Connection string
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
```

**When using mysql CLI via web container:**
```bash
# From web container
mysql -h database -P 3306 -u root -pRootPassword456! circulation_dashboard
```

### SSH Access

```bash
# Connect to NAS
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254

# With command execution
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'COMMAND'
```

### Docker Commands (via SSH)

**All Docker commands require sudo:**
```bash
# Execute with sudo
echo Mojave48ice | sudo -S -k /usr/local/bin/docker COMMAND

# Common Docker Compose commands
echo Mojave48ice | sudo -S -k /usr/local/bin/docker compose ps
echo Mojave48ice | sudo -S -k /usr/local/bin/docker compose logs web
echo Mojave48ice | sudo -S -k /usr/local/bin/docker compose restart web
echo Mojave48ice | sudo -S -k /usr/local/bin/docker compose up -d
```

### Execute Commands in Containers

```bash
# Via web container (has database access)
echo Mojave48ice | sudo -S -k /usr/local/bin/docker exec circulation_web php /var/www/html/SCRIPT.php

# Via database container (direct database access)
echo Mojave48ice | sudo -S -k /usr/local/bin/docker exec -i circulation_db mariadb -uroot -pRootPassword456! circulation_dashboard
```

### File Transfer (SSH Cat Method)

**SCP is disabled on Synology, use SSH cat instead:**

```bash
# Upload file to NAS
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'cat > /volume1/docker/nwdownloads/web/FILE.php' < local_file.php

# Download file from NAS
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'cat /volume1/docker/nwdownloads/web/FILE.php' > local_file.php
```

---

## 🗂️ PRODUCTION PATHS

**NAS File System:**
- Project root: `/volume1/docker/nwdownloads/`
- Web files: `/volume1/docker/nwdownloads/web/`
- Database init: `/volume1/docker/nwdownloads/db_init/`
- SQL scripts: `/volume1/docker/nwdownloads/sql/`
- Docker Compose: `/volume1/docker/nwdownloads/docker-compose.yml`

**Container Internal Paths:**
- Web root: `/var/www/html/` (inside circulation_web container)
- Database data: `/var/lib/mysql/` (inside circulation_db container)

---

## 📦 DOCKER SERVICES

**Service Names (for Docker network connections):**
- `database` - MariaDB 10.11 container (accessible as hostname "database" from web container)
- `web` - PHP 8.2 + Apache container

**Container Names:**
- `circulation_db` - Database container
- `circulation_web` - Web server container

**Network:**
- `circulation_network` - Internal Docker network connecting services

---

## 🔧 COMMON OPERATIONS

### 1. Deploy Updated PHP File

```bash
# Upload file
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'cat > /volume1/docker/nwdownloads/web/upload.php' < web/upload.php

# Restart web container
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'cd /volume1/docker/nwdownloads && echo Mojave48ice | sudo -S -k /usr/local/bin/docker compose restart web'
```

### 2. Execute SQL Script on Production Database

**Method 1: Via docker exec (preferred for SQL files)**
```bash
# Upload SQL file
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'cat > /tmp/script.sql' < sql/script.sql

# Execute SQL
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'echo Mojave48ice | sudo -S -k /usr/local/bin/docker exec -i circulation_db mariadb -uroot -pRootPassword456! circulation_dashboard < /tmp/script.sql'
```

**Method 2: Via PHP in web container (for complex operations)**
```bash
# Create PHP script with database operations
# Upload to web directory
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'cat > /volume1/docker/nwdownloads/web/operation.php' < operation.php

# Execute via web container
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'echo Mojave48ice | sudo -S -k /usr/local/bin/docker exec circulation_web php /var/www/html/operation.php'
```

### 3. Check for Database Issues

```bash
# Check for duplicates
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'echo Mojave48ice | sudo -S -k /usr/local/bin/docker exec -i circulation_db mariadb -uroot -pRootPassword456! circulation_dashboard -e "SELECT snapshot_date, sub_num, paper_code, COUNT(*) as count FROM subscriber_snapshots GROUP BY snapshot_date, sub_num, paper_code HAVING count > 1 LIMIT 10;"'
```

### 4. View Container Logs

```bash
# Web container logs
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'echo Mojave48ice | sudo -S -k /usr/local/bin/docker logs circulation_web --tail 50'

# Database container logs
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'echo Mojave48ice | sudo -S -k /usr/local/bin/docker logs circulation_db --tail 50'
```

### 5. Restart Containers

```bash
# Restart specific service
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'cd /volume1/docker/nwdownloads && echo Mojave48ice | sudo -S -k /usr/local/bin/docker compose restart web'

# Restart all services
sshpass -p 'Mojave48ice' ssh -o StrictHostKeyChecking=no it@192.168.1.254 'cd /volume1/docker/nwdownloads && echo Mojave48ice | sudo -S -k /usr/local/bin/docker compose restart'
```

---

## 🚨 TROUBLESHOOTING QUICK REFERENCE

### Error: "Connection refused" or "No such file or directory"
**Problem:** Wrong hostname or trying to use socket that doesn't exist
**Solution:** Use `database` as hostname (Docker Compose service name)

### Error: "Access denied"
**Problem:** Wrong credentials
**Solution:** Check docker-compose.yml for current credentials (root/RootPassword456!)

### Error: "Permission denied" (Docker socket)
**Problem:** Docker commands require sudo
**Solution:** Use `echo Mojave48ice | sudo -S -k /usr/local/bin/docker ...`

### Error: "sudo: 3 incorrect password attempts"
**Problem:** Password prompt being consumed by pipe or heredoc
**Solution:** Use `-S -k` flags and echo password, or use single SSH session

---

## ⚡ THE 3-ATTEMPT RULE

**Maximum attempts for any operation: 3**

**If you need attempt #4:**
- ❌ You failed to read the documentation
- ❌ You are guessing instead of using documented approach
- ✅ STOP and re-read this checklist
- ✅ STOP and ask user for clarification

**Success looks like:**
- Attempt 1: Works (read docs correctly)
- Attempt 2 at most: Works (typo or minor adjustment)
- Attempt 3+: You're doing it wrong - STOP

---

## 📚 MORE INFORMATION

- **Full project config:** `.claude/CLAUDE.md`
- **Docker configuration:** `docker-compose.yml`
- **Deployment guide:** `docs/DEPLOYMENT-STRATEGY.md`
- **Database schema:** `sql/` directory

---

**Last Updated:** 2025-12-05
**Created By:** Claude (after learning the hard way to read documentation first)
