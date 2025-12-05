# SSH Connection Quick Start Guide

## 🚀 Super Fast Setup (3 Minutes)

### Step 1: Configure SSH Credentials

1. **Copy the template:**
   ```bash
   cd /Users/johncorbin/Desktop/projs/nwdownloads/synology_setup
   cp .env.ssh .env.ssh.backup
   ```

2. **Edit `.env.ssh` with your actual values:**
   ```bash
   nano .env.ssh
   ```

3. **Update these critical fields:**
   ```
   SSH_HOST=192.168.1.100          ← Your Synology IP address
   SSH_USER=admin                   ← Your admin username
   SSH_PASSWORD=your_password       ← Your admin password
   DB_PASSWORD=your_db_password     ← Database password you'll create
   ```

4. **Save:** Press `Ctrl+X`, then `Y`, then `Enter`

---

### Step 2: Connect to Your NAS

**Option A: Use the helper script (easiest):**
```bash
cd /Users/johncorbin/Desktop/projs/nwdownloads/synology_setup
./connect.sh
```

**Option B: Manual connection:**
```bash
ssh admin@192.168.1.100
```
*(Replace with your actual IP address)*

---

## 📂 Helper Scripts Available

### `connect.sh` - Quick SSH Connection
Automatically connects using credentials from `.env.ssh`

**Usage:**
```bash
./connect.sh
```

---

### `upload_files.sh` - Upload Dashboard Files
Transfers all web files and scripts to your NAS

**Usage:**
```bash
./upload_files.sh
```

**What it uploads:**
- ✅ Web dashboard files (`index.html`, `api.php`, `app.js`, etc.)
- ✅ Python import script
- ✅ `.env` configuration (if exists)

---

## 🔐 Security Best Practices

### Option 1: Password (Quick Setup)
- ✅ Fast to set up
- ⚠️ Less secure
- Use for initial setup only

### Option 2: SSH Keys (Recommended)

**Generate SSH key pair:**
```bash
ssh-keygen -t rsa -b 4096 -f ~/.ssh/synology_rsa
```

**Copy public key to NAS:**
```bash
ssh-copy-id -i ~/.ssh/synology_rsa.pub admin@192.168.1.100
```

**Update `.env.ssh`:**
```
SSH_KEY_PATH=~/.ssh/synology_rsa
# Comment out SSH_PASSWORD
```

**Benefits:**
- 🔒 Much more secure
- 🚀 No password typing
- ✅ Can disable password authentication

---

## 📝 Complete Workflow

### First-Time Setup:

```bash
# 1. Navigate to setup folder
cd /Users/johncorbin/Desktop/projs/nwdownloads/synology_setup

# 2. Configure credentials
nano .env.ssh

# 3. Enable SSH on Synology
# (Control Panel → Terminal & SNMP → Enable SSH)

# 4. Test connection
./connect.sh

# 5. Follow SSH_SETUP_GUIDE.md to create database
```

### After Database Setup:

```bash
# Upload all files to NAS
./upload_files.sh

# Verify files uploaded
./connect.sh
ls -la /volume1/circulation/web/
exit
```

---

## 🐛 Troubleshooting

### "Permission denied (publickey,password)"
→ Check SSH is enabled on Synology (Control Panel → Terminal & SNMP)
→ Verify username/password in `.env.ssh`

### "Connection refused"
→ Check SSH_HOST IP address is correct
→ Ping the NAS: `ping 192.168.1.100`
→ Verify SSH port (default is 22)

### "scp: command not found"
→ Your Mac should have `scp` by default
→ Try updating macOS or use FileZilla instead

### "No route to host"
→ NAS and Mac must be on same network
→ Check firewall settings

---

## 📋 File Locations Reference

### Local (Your Mac):
```
/Users/johncorbin/Desktop/projs/nwdownloads/
├── web/                          ← Dashboard files
│   ├── index.html
│   ├── api.php
│   └── assets/app.js
├── synology_setup/               ← Setup scripts
│   ├── .env.ssh                  ← SSH credentials (DO NOT COMMIT)
│   ├── connect.sh                ← SSH helper
│   ├── upload_files.sh           ← File upload helper
│   └── SSH_SETUP_GUIDE.md        ← Database setup guide
└── .env                          ← App configuration
```

### Remote (Synology NAS):
```
/volume1/circulation/
├── web/                          ← Dashboard (public)
│   ├── index.html
│   ├── api.php
│   └── assets/app.js
├── scripts/                      ← Python scripts (private)
│   └── 3_import_to_database.py
├── data/                         ← CSV files (private)
│   ├── subscriptions_latest.csv
│   ├── vacations_latest.csv
│   └── rates_latest.csv
└── .env                          ← Configuration (private)
```

---

## ⚡ Quick Commands Cheat Sheet

```bash
# Connect to NAS
./connect.sh

# Upload files
./upload_files.sh

# Manual SSH
ssh admin@192.168.1.100

# Manual file upload
scp file.txt admin@192.168.1.100:/volume1/circulation/

# Check if NAS is reachable
ping 192.168.1.100

# Test database connection (once on NAS)
/usr/local/mariadb10/bin/mysql -u dashboard_user -p circulation_dashboard
```

---

## 🎯 Next Steps

1. ✅ Configure `.env.ssh` with your credentials
2. ✅ Test connection with `./connect.sh`
3. ✅ Follow `SSH_SETUP_GUIDE.md` to create database
4. ✅ Upload files with `./upload_files.sh`
5. ✅ Configure Web Station to serve `/volume1/circulation/web`
6. ✅ Test dashboard in browser!

---

## 🔒 IMPORTANT: Security Checklist

Before going to production:

- [ ] Change default database password
- [ ] Update password in `.env.ssh`
- [ ] Update password in `/volume1/circulation/.env`
- [ ] Update password in `api.php` (line 21)
- [ ] Add `.env.ssh` to `.gitignore`
- [ ] Consider using SSH keys instead of password
- [ ] Disable SSH after setup (Control Panel → Terminal & SNMP)
- [ ] Enable firewall rules (if exposing to internet)
- [ ] Set up HTTPS (if exposing to internet)

---

**Need help? Check `SSH_SETUP_GUIDE.md` for detailed step-by-step instructions!**
