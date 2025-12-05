# PhpMyAdmin Setup Instructions

## 📋 Quick Steps to Set Up Database

### Step 1: Install PhpMyAdmin on Synology

1. Open **DSM** (Synology DiskStation Manager)
2. Go to **Package Center**
3. Search for **phpMyAdmin**
4. Click **Install**
5. Wait for installation to complete

---

### Step 2: Access PhpMyAdmin

**URL:** `http://192.168.1.254:80/phpMyAdmin`

Or access through DSM:
1. Open **Main Menu**
2. Click **phpMyAdmin** icon

---

### Step 3: Log In to PhpMyAdmin

**Username:** `root`
**Password:** *(MariaDB root password - set during MariaDB installation)*

If you don't know the root password:
- Check Synology DSM → **MariaDB 10** package → **Settings**
- Or reset it through MariaDB package settings

---

### Step 4: Run the SQL Script

1. **In PhpMyAdmin**, click the **SQL** tab at the top
2. **Open the file:** `/Users/johncorbin/Desktop/projs/nwdownloads/synology_setup/PHPMYADMIN_SETUP.sql`
3. **Copy ALL the SQL code** (Cmd+A, Cmd+C)
4. **Paste into the SQL tab** in PhpMyAdmin
5. **Click "Go"** button at the bottom

**What this script does:**
- ✅ Creates database: `circulation_dashboard`
- ✅ Creates user: `circ_dash` with password `Barnaby358Jones`
- ✅ Grants all privileges to the user
- ✅ Creates 5 tables:
  - `daily_snapshots` - Daily metrics by paper
  - `vacation_snapshots` - Vacation statistics
  - `rate_distribution` - Rate package breakdown
  - `dashboard_users` - Authentication users
  - `import_log` - Import history tracking
- ✅ Runs verification queries

---

### Step 5: Verify Setup

After running the script, you should see output showing:

**Databases:**
```
+---------------------------+
| Database                  |
+---------------------------+
| circulation_dashboard     |
+---------------------------+
```

**Tables:**
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

**User:**
```
+-----------+-----------+
| User      | Host      |
+-----------+-----------+
| circ_dash | localhost |
+-----------+-----------+
```

---

### Step 6: Test Database Connection

**Method 1: PhpMyAdmin**
1. In left sidebar, click **circulation_dashboard**
2. You should see all 5 tables listed
3. Click any table to view its structure

**Method 2: SSH Command Line**
```bash
# From your Mac terminal
ssh it@192.168.1.254

# On the NAS
/usr/local/mariadb10/bin/mysql -u circ_dash -p'Barnaby358Jones' circulation_dashboard -e "SHOW TABLES;"
```

You should see all 5 tables listed.

---

## 🔧 Troubleshooting

### "Access denied for user 'root'@'localhost'"
**Solution:**
1. Open **DSM** → **Package Center**
2. Find **MariaDB 10** → Click **Settings**
3. Check or reset the root password
4. Try logging in again

---

### "Database already exists"
**Solution:** This is fine! The script uses `IF NOT EXISTS`, so it won't cause errors.

---

### "User 'circ_dash' already exists"
**Solution:**
1. Either drop the existing user first:
   ```sql
   DROP USER 'circ_dash'@'localhost';
   ```
2. Or change the username in the SQL script

---

### PhpMyAdmin shows blank page
**Solution:**
1. Check **Web Station** is running (DSM → Web Station)
2. Enable PHP 7.4+ in Web Station settings
3. Restart Web Station

---

## 🔒 Security Notes

### Change Default Passwords!
Before going to production:

1. **Database user password** - Line 15 in SQL script
2. **Update `.env.ssh` file** with new password
3. **Update `api.php`** (line 21) with new password

### PhpMyAdmin Access
- Only accessible on local network by default
- Don't expose to public internet without SSL/authentication
- Consider disabling after setup if not needed regularly

---

## ✅ After Setup is Complete

### Next Steps:

1. **Create directory structure:**
   ```bash
   ssh it@192.168.1.254
   sudo mkdir -p /volume1/circulation/{data,scripts,web/assets}
   sudo chmod -R 755 /volume1/circulation
   ```

2. **Upload web files:**
   ```bash
   # From your Mac
   cd /Users/johncorbin/Desktop/projs/nwdownloads/synology_setup
   ./upload_files.sh
   ```

3. **Configure Web Station:**
   - DSM → Web Station
   - Create new virtual host or use default
   - Point document root to `/volume1/circulation/web`

4. **Test the dashboard:**
   - Open browser: `http://192.168.1.254/circulation`
   - Should see dashboard (with "No data" until import)

---

## 📝 Database Credentials Summary

**Save these for later use:**

```
Database Host: localhost (or 192.168.1.254 from remote)
Database Port: 3306
Database Name: circulation_dashboard
Database User: circ_dash
Database Password: Barnaby358Jones
```

**You'll need these for:**
- PHP `api.php` configuration
- Python import script `.env` file
- Any database clients (DBeaver, MySQL Workbench, etc.)

---

## 🎉 Success Checklist

After completing this guide:

- [ ] PhpMyAdmin installed and accessible
- [ ] Logged in with root credentials
- [ ] SQL script executed successfully
- [ ] Database `circulation_dashboard` created
- [ ] User `circ_dash` created
- [ ] All 5 tables created
- [ ] Verified tables in PhpMyAdmin
- [ ] Tested connection from command line
- [ ] Passwords documented safely

**All checked? You're ready to move to web file upload!** 🚀
