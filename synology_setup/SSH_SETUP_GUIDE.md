# SSH Setup Guide - Synology Database Installation

## Prerequisites

Before we start, you'll need:
- [ ] SSH enabled on your Synology
- [ ] Admin password
- [ ] Terminal access (Mac Terminal or Windows PowerShell)
- [ ] MariaDB 10 installed (Package Center)

---

## Step 1: Enable SSH on Synology

1. Open **DSM** (Synology web interface)
2. Go to **Control Panel** → **Terminal & SNMP**
3. Check **Enable SSH service**
4. Port: `22` (default)
5. Click **Apply**

---

## Step 2: Connect via SSH

From your Mac, open Terminal and run:

```bash
ssh admin@YOUR_SYNOLOGY_IP
```

**Replace `YOUR_SYNOLOGY_IP`** with your Synology's IP address (e.g., `192.168.1.100`)

When prompted:
- Type `yes` to accept the fingerprint
- Enter your admin password

You should see:
```
admin@YourNAS:~$
```

---

## Step 3: Check MariaDB Installation

Run this to verify MariaDB is installed:

```bash
/usr/local/mariadb10/bin/mysql --version
```

You should see something like:
```
mysql  Ver 15.1 Distrib 10.x.x-MariaDB
```

If you get "command not found", install MariaDB 10 from Package Center first.

---

## Step 4: Create Database and User

I'll provide you with a complete setup script. Run these commands one at a time:

### 4A. Connect to MariaDB as root:

```bash
sudo /usr/local/mariadb10/bin/mysql -u root -p
```

**Enter your Synology admin password when prompted.**

You'll see:
```
MariaDB [(none)]>
```

### 4B. Create Database:

Copy and paste each command:

```sql
-- Create the database
CREATE DATABASE IF NOT EXISTS circulation_dashboard
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

-- Verify database created
SHOW DATABASES LIKE 'circulation%';
```

You should see:
```
+----------------------------+
| Database                   |
+----------------------------+
| circulation_dashboard      |
+----------------------------+
```

### 4C. Create User and Grant Permissions:

```sql
-- Create user (change password!)
CREATE USER IF NOT EXISTS 'dashboard_user'@'localhost'
IDENTIFIED BY 'Change_This_Password_123!';

-- Grant all privileges on the database
GRANT ALL PRIVILEGES ON circulation_dashboard.*
TO 'dashboard_user'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;

-- Verify user created
SELECT User, Host FROM mysql.user WHERE User = 'dashboard_user';
```

You should see:
```
+----------------+-----------+
| User           | Host      |
+----------------+-----------+
| dashboard_user | localhost |
+----------------+-----------+
```

### 4D. Switch to the new database:

```sql
USE circulation_dashboard;
```

---

## Step 5: Create Database Tables

Now we'll create all the tables. Copy and paste this entire block:

```sql
-- Table 1: Daily snapshots by paper
CREATE TABLE IF NOT EXISTS daily_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    paper_name VARCHAR(100),
    business_unit VARCHAR(50),
    total_active INT NOT NULL DEFAULT 0,
    on_vacation INT NOT NULL DEFAULT 0,
    deliverable INT NOT NULL DEFAULT 0,
    mail_delivery INT NOT NULL DEFAULT 0,
    carrier_delivery INT NOT NULL DEFAULT 0,
    digital_only INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_daily_snapshot (snapshot_date, paper_code),
    INDEX idx_date (snapshot_date),
    INDEX idx_paper (paper_code),
    INDEX idx_business_unit (business_unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 2: Vacation statistics
CREATE TABLE IF NOT EXISTS vacation_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    active_vacations INT NOT NULL DEFAULT 0,
    scheduled_next_7days INT NOT NULL DEFAULT 0,
    scheduled_next_30days INT NOT NULL DEFAULT 0,
    returning_this_week INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vac_snapshot (snapshot_date, paper_code),
    INDEX idx_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 3: Rate package distribution
CREATE TABLE IF NOT EXISTS rate_distribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    snapshot_date DATE NOT NULL,
    paper_code VARCHAR(10) NOT NULL,
    rate_id INT NOT NULL,
    rate_description TEXT,
    subscriber_count INT NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2),
    rank_position INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date_paper (snapshot_date, paper_code),
    INDEX idx_rate (rate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 4: Dashboard users
CREATE TABLE IF NOT EXISTS dashboard_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    email VARCHAR(100),
    role VARCHAR(20) DEFAULT 'viewer',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table 5: Import log
CREATE TABLE IF NOT EXISTS import_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_type VARCHAR(50),
    file_name VARCHAR(255),
    records_processed INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'success',
    error_message TEXT,
    INDEX idx_date (import_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verify tables created
SHOW TABLES;
```

You should see:
```
+----------------------------------+
| Tables_in_circulation_dashboard  |
+----------------------------------+
| daily_snapshots                  |
| dashboard_users                  |
| import_log                       |
| rate_distribution                |
| vacation_snapshots               |
+----------------------------------+
```

### 5A. Verify Table Structure:

```sql
DESCRIBE daily_snapshots;
```

You should see all the columns listed.

### 5B. Exit MariaDB:

```sql
EXIT;
```

You're back to the shell:
```
admin@YourNAS:~$
```

---

## Step 6: Create Directory Structure

Create folders for the dashboard:

```bash
# Create main directories
sudo mkdir -p /volume1/circulation/{data,scripts,web/assets}

# Set permissions
sudo chmod -R 755 /volume1/circulation

# Verify created
ls -la /volume1/circulation
```

You should see:
```
drwxr-xr-x  data
drwxr-xr-x  scripts
drwxr-xr-x  web
```

---

## Step 7: Create .env Configuration File

Create the configuration file:

```bash
# Create .env file
sudo nano /volume1/circulation/.env
```

Nano editor will open. Copy and paste this:

```
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=circulation_dashboard
DB_USER=dashboard_user
DB_PASSWORD=Change_This_Password_123!

# Paths
DATA_DIR=/volume1/circulation/data
WEB_DIR=/volume1/circulation/web
SCRIPTS_DIR=/volume1/circulation/scripts

# Files
SUBSCRIPTIONS_FILE=/volume1/circulation/data/subscriptions_latest.csv
VACATIONS_FILE=/volume1/circulation/data/vacations_latest.csv
RATES_FILE=/volume1/circulation/data/rates_latest.csv
```

**IMPORTANT:** Change `DB_PASSWORD` to match what you set in Step 4C!

Save and exit:
- Press `Ctrl+X`
- Press `Y` to confirm
- Press `Enter` to save

---

## Step 8: Test Database Connection

Let's verify everything works:

```bash
# Test connection
/usr/local/mariadb10/bin/mysql -u dashboard_user -p circulation_dashboard
```

Enter the password you set.

If successful, you'll see:
```
MariaDB [circulation_dashboard]>
```

Test a query:
```sql
SELECT COUNT(*) FROM daily_snapshots;
```

Should return:
```
+----------+
| COUNT(*) |
+----------+
|        0 |
+----------+
```

Exit:
```sql
EXIT;
```

---

## Step 9: Install Python 3 (if not already installed)

Check if Python 3 is installed:

```bash
python3 --version
```

If not found, install via DSM:
1. Open **Package Center**
2. Search for "Python 3"
3. Click **Install**

Or use Docker (recommended):

```bash
# Pull Python Docker image
sudo docker pull python:3.11-slim
```

---

## Step 10: Upload Data Import Script

From your Mac, upload the import script:

```bash
# From your Mac terminal (not SSH)
scp /Users/johncorbin/Desktop/projs/nwdownloads/synology_setup/3_import_to_database.py admin@YOUR_SYNOLOGY_IP:/volume1/circulation/scripts/

# Upload requirements file
echo "mysql-connector-python==8.2.0" > /tmp/requirements.txt
scp /tmp/requirements.txt admin@YOUR_SYNOLOGY_IP:/volume1/circulation/scripts/
```

---

## Step 11: Install Python Dependencies

Back in SSH:

```bash
# Install pip if needed
sudo python3 -m ensurepip

# Install MySQL connector
sudo python3 -m pip install mysql-connector-python
```

---

## Step 12: Test Import Script

Let's do a quick test (will show errors about missing CSV files, which is expected):

```bash
cd /volume1/circulation/scripts
python3 3_import_to_database.py
```

You should see:
```
================================================================================
CIRCULATION DASHBOARD - DATA IMPORT
================================================================================
✓ Connected to database: circulation_dashboard

Loading reference data...
✗ Rates file not found: /volume1/circulation/data/rates_latest.csv
```

This is normal! We haven't uploaded the Newzware exports yet.

---

## ✅ Database Setup Complete!

Your database is ready! Here's what we've set up:

- [x] MariaDB database: `circulation_dashboard`
- [x] Database user: `dashboard_user`
- [x] 5 tables created and ready
- [x] Folder structure created
- [x] .env configuration file
- [x] Python environment ready
- [x] Import script uploaded

---

## 🎯 Next Steps

### Immediate:
1. **Copy your .env password** - You'll need it for the PHP API
2. **Upload web files** to `/volume1/circulation/web/`
3. **Export Newzware data** - Run your 3 queries
4. **Upload CSV files** to `/volume1/circulation/data/`
5. **Run import script** - `python3 3_import_to_database.py`
6. **View dashboard** - Open in browser!

---

## 🔒 Security Notes

### Update These Passwords:
- [ ] Database password (you did this in Step 4C)
- [ ] Update .env file with same password
- [ ] Update api.php with same password (when you upload it)

### Disable SSH (After Setup):
Once everything is working:
1. Go to **Control Panel** → **Terminal & SNMP**
2. Uncheck **Enable SSH service**
3. Click **Apply**

You can always re-enable it later if needed.

---

## 🐛 Troubleshooting

### Can't connect via SSH
→ Check SSH is enabled in Control Panel → Terminal & SNMP

### Permission denied errors
→ Add `sudo` before commands

### MariaDB command not found
→ Use full path: `/usr/local/mariadb10/bin/mysql`

### Can't create database
→ Make sure you're connected as root: `sudo mysql -u root -p`

### Forgot database password
→ Reconnect as root and run:
```sql
ALTER USER 'dashboard_user'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
```

---

## 📝 Save This Information

**Write down these credentials:**

```
Database Host: localhost
Database Name: circulation_dashboard
Database User: dashboard_user
Database Password: [your password]
Database Port: 3306
```

**You'll need these for:**
- PHP API configuration
- Python import script
- Any future database access

---

## ✅ Verification Checklist

Before moving on, verify:

- [ ] Can SSH into Synology
- [ ] MariaDB is running
- [ ] Database `circulation_dashboard` exists
- [ ] User `dashboard_user` can connect
- [ ] All 5 tables exist
- [ ] Folders created: `/volume1/circulation/*`
- [ ] .env file created with correct password
- [ ] Python 3 installed
- [ ] mysql-connector-python installed
- [ ] Import script uploaded

**All checked? You're ready for data import!** 🚀

---

## Need Help?

If you get stuck:
1. Check the error message carefully
2. Try the troubleshooting section above
3. Let me know the exact error - I'll help debug!

**Ready to proceed? Let me know when you've completed the SSH setup!**
