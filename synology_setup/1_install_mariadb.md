# Day 1: Install MariaDB on Synology

## Step 1: Install MariaDB Package

1. Open **Package Center** on Synology DSM
2. Search for "MariaDB 10"
3. Click **Install**
4. Wait for installation to complete

## Step 2: Initial Configuration

1. Open **MariaDB 10** from Package Center
2. Click **Open** to access phpMyAdmin
3. Login with:
   - Username: `root`
   - Password: (set during installation)

## Step 3: Create Dashboard Database

In phpMyAdmin:

1. Click **Databases** tab
2. Create new database: `circulation_dashboard`
3. Collation: `utf8mb4_general_ci`
4. Click **Create**

## Step 4: Create Database User

1. Click **Users** tab
2. Click **Add user account**
3. Fill in:
   - Username: `dashboard_user`
   - Host: `localhost`
   - Password: [create strong password]
   - Re-type password
4. Under **Database for user account**:
   - Select: `Grant all privileges on database "circulation_dashboard"`
5. Click **Go**

## Step 5: Save Credentials

Create file on Synology: `/volume1/docker/circulation/.env`

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=circulation_dashboard
DB_USER=dashboard_user
DB_PASSWORD=[your password]
```

## Step 6: Test Connection

In phpMyAdmin, click **SQL** tab and run:

```sql
SHOW DATABASES;
```

You should see `circulation_dashboard` in the list.

---

**✅ Checklist:**
- [ ] MariaDB installed
- [ ] Database created
- [ ] User created with permissions
- [ ] Credentials saved to .env file
- [ ] Connection tested

**Next:** Day 1 Part 2 - Create Database Tables
