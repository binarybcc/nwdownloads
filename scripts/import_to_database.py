#!/usr/bin/env python3
"""
Circulation Dashboard - Data Import Script
Imports Newzware CSV exports into MariaDB database
"""

import csv
import sys
from collections import defaultdict
from datetime import date, datetime

import mysql.connector

# Database configuration
DB_CONFIG = {
    "host": "localhost",
    "port": 3306,
    "database": "circulation_dashboard",
    "user": "circ_dash",
    "password": "Barnaby358@Jones!",
}

# File paths
DATA_DIR = "/volume1/circulation/data"
SUBSCRIPTIONS_FILE = f"{DATA_DIR}/subscriptions_latest.csv"
VACATIONS_FILE = f"{DATA_DIR}/vacations_latest.csv"
RATES_FILE = f"{DATA_DIR}/rates_latest.csv"

# Paper/edition names mapping
PAPER_NAMES = {
    "TJ": "The Journal",
    "TA": "The Advertiser",
    "TR": "The Register",
    "LJ": "Lake Journal",
    "WRN": "Wyoming Review News",
    "FN": "Former News",  # Excluded from active counts
}

# Business unit mapping
BUSINESS_UNITS = {
    "TJ": "South Carolina",
    "TA": "Michigan",
    "TR": "Wyoming",
    "LJ": "Wyoming",
    "WRN": "Wyoming",
    "FN": "Sold",
}


def connect_db():
    """Connect to MariaDB database"""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        print("✅ Connected to database")
        return conn
    except mysql.connector.Error as err:
        print(f"❌ Database connection failed: {err}")
        sys.exit(1)


def load_rates(cursor):
    """Load rate-to-edition mapping from CSV"""
    print(f"\n📖 Loading rates from {RATES_FILE}...")
    rate_map = {}

    with open(RATES_FILE, "r") as f:
        reader = csv.DictReader(f)
        for row in reader:
            # Map rate ID to edition
            # Column 56 is 'rr_code' (rate ID), Column 6 is 'rr_edition'
            rate_id = row.get("rr_code", "").strip()
            edition = row.get("rr_edition", "").strip()
            rate_desc = row.get("rr_desc", "").strip()

            if rate_id and edition:
                rate_map[rate_id] = {"edition": edition, "description": rate_desc}

    print(f"   Loaded {len(rate_map)} rate mappings")
    return rate_map


def load_vacations(cursor):
    """Load vacation holds from CSV"""
    print(f"\n📖 Loading vacations from {VACATIONS_FILE}...")
    vacation_map = defaultdict(list)
    today = date.today()

    with open(VACATIONS_FILE, "r") as f:
        reader = csv.DictReader(f)
        for row in reader:
            vac_id = row["vd_sp_id"].strip()
            beg_date_str = row["vd_beg_date"].strip()
            end_date_str = row["vd_end_date"].strip()

            # Parse dates (format: MM/DD/YY)
            try:
                if beg_date_str:
                    beg_date = datetime.strptime(beg_date_str, "%m/%d/%y").date()
                else:
                    continue

                if end_date_str:
                    end_date = datetime.strptime(end_date_str, "%m/%d/%y").date()
                else:
                    end_date = None  # Open-ended vacation

                # Check if vacation is active today
                is_active = beg_date <= today and (end_date is None or end_date >= today)

                vacation_map[vac_id].append(
                    {"beg_date": beg_date, "end_date": end_date, "is_active": is_active}
                )
            except ValueError:
                continue

    print(f"   Loaded {len(vacation_map)} subscribers with vacations")
    return vacation_map


def process_subscriptions(cursor, rate_map, vacation_map):
    """Process subscriptions and insert into database"""
    print(f"\n📖 Loading subscriptions from {SUBSCRIPTIONS_FILE}...")

    snapshot_date = date.today()
    stats_by_paper = defaultdict(
        lambda: {
            "total_active": 0,
            "on_vacation": 0,
            "deliverable": 0,
            "mail_delivery": 0,
            "carrier_delivery": 0,
            "digital_only": 0,
        }
    )

    with open(SUBSCRIPTIONS_FILE, "r") as f:
        reader = csv.DictReader(f)
        for row in reader:
            sp_num = row["sp_num"].strip()
            sp_stat = row["sp_stat"].strip()
            sp_rate_id = row["sp_rate_id"].strip()
            sp_route = row["sp_route"].strip()
            sp_vac_ind = row["sp_vac_ind"].strip()

            # Only process active subscriptions
            if sp_stat != "A":
                continue

            # Get edition from rate mapping
            if sp_rate_id not in rate_map:
                continue  # Skip if rate not found

            edition = rate_map[sp_rate_id]["edition"]

            # Skip if not a known paper
            if edition not in PAPER_NAMES:
                continue

            # Determine delivery type
            route_upper = sp_route.upper()
            if route_upper == "MAIL":
                delivery_type = "mail"
            elif route_upper == "INTERNET":
                delivery_type = "digital"
            else:
                delivery_type = "carrier"

            # Check if on vacation
            is_on_vacation = False
            if sp_vac_ind != "0" and sp_vac_ind in vacation_map:
                for vac in vacation_map[sp_vac_ind]:
                    if vac["is_active"]:
                        is_on_vacation = True
                        break

            # Update stats
            stats_by_paper[edition]["total_active"] += 1
            if is_on_vacation:
                stats_by_paper[edition]["on_vacation"] += 1
            else:
                stats_by_paper[edition]["deliverable"] += 1

            if delivery_type == "mail":
                stats_by_paper[edition]["mail_delivery"] += 1
            elif delivery_type == "carrier":
                stats_by_paper[edition]["carrier_delivery"] += 1
            elif delivery_type == "digital":
                stats_by_paper[edition]["digital_only"] += 1

    # Insert into database
    print(f"\n💾 Inserting data into database...")

    for edition, stats in stats_by_paper.items():
        paper_name = PAPER_NAMES[edition]
        business_unit = BUSINESS_UNITS[edition]

        cursor.execute(
            """
            INSERT INTO daily_snapshots
            (snapshot_date, paper_code, paper_name, business_unit,
             total_active, on_vacation, deliverable,
             mail_delivery, carrier_delivery, digital_only)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
             total_active = VALUES(total_active),
             on_vacation = VALUES(on_vacation),
             deliverable = VALUES(deliverable),
             mail_delivery = VALUES(mail_delivery),
             carrier_delivery = VALUES(carrier_delivery),
             digital_only = VALUES(digital_only)
        """,
            (
                snapshot_date,
                edition,
                paper_name,
                business_unit,
                stats["total_active"],
                stats["on_vacation"],
                stats["deliverable"],
                stats["mail_delivery"],
                stats["carrier_delivery"],
                stats["digital_only"],
            ),
        )

    print(f"   Inserted {len(stats_by_paper)} paper snapshots")
    return stats_by_paper


def log_import(cursor, stats_by_paper):
    """Log import to import_log table"""
    total_records = sum(stats["total_active"] for stats in stats_by_paper.values())

    cursor.execute(
        """
        INSERT INTO import_log (import_date, records_processed, status, notes)
        VALUES (%s, %s, %s, %s)
    """,
        (
            datetime.now(),
            total_records,
            "success",
            f"Imported {len(stats_by_paper)} papers",
        ),
    )

    print(f"\n✅ Import logged: {total_records} total subscriptions")


def main():
    """Main import process"""
    print("=" * 60)
    print("Circulation Dashboard - Data Import")
    print("=" * 60)

    conn = connect_db()
    cursor = conn.cursor()

    try:
        # Load reference data
        rate_map = load_rates(cursor)
        vacation_map = load_vacations(cursor)

        # Process subscriptions
        stats_by_paper = process_subscriptions(cursor, rate_map, vacation_map)

        # Log the import
        log_import(cursor, stats_by_paper)

        # Commit transaction
        conn.commit()

        # Print summary
        print("\n" + "=" * 60)
        print("📊 IMPORT SUMMARY")
        print("=" * 60)
        for edition in sorted(stats_by_paper.keys()):
            if edition == "FN":
                continue  # Skip sold paper
            stats = stats_by_paper[edition]
            print(
                f"{edition:4s} - {PAPER_NAMES[edition]:20s} | "
                + f"Active: {stats['total_active']:5d} | "
                + f"Vacation: {stats['on_vacation']:3d} | "
                + f"Deliverable: {stats['deliverable']:5d}"
            )

        total = sum(s["total_active"] for e, s in stats_by_paper.items() if e != "FN")
        total_vac = sum(s["on_vacation"] for e, s in stats_by_paper.items() if e != "FN")
        total_del = sum(s["deliverable"] for e, s in stats_by_paper.items() if e != "FN")

        print("-" * 60)
        print(
            f"TOTAL (excluding FN)              | "
            + f"Active: {total:5d} | "
            + f"Vacation: {total_vac:3d} | "
            + f"Deliverable: {total_del:5d}"
        )
        print("=" * 60)
        print("\n✅ Import completed successfully!")
        print(f"\n🌐 View dashboard at: http://192.168.1.254:8080")

    except Exception as e:
        conn.rollback()
        print(f"\n❌ Import failed: {e}")
        import traceback

        traceback.print_exc()
        sys.exit(1)
    finally:
        cursor.close()
        conn.close()


if __name__ == "__main__":
    main()
