#!/usr/bin/env python3
"""
Circulation Dashboard - Data Import Script
Imports Newzware exports into MariaDB for dashboard display

Run daily after Newzware exports are complete
"""

import csv
import os
from collections import defaultdict
from datetime import datetime

import mysql.connector

# Configuration
CONFIG = {
    "db_host": "localhost",
    "db_port": 3306,
    "db_name": "circulation_dashboard",
    "db_user": "dashboard_user",
    "db_password": "YOUR_PASSWORD_HERE",  # Update this!
    # File paths on Synology
    "subscriptions_file": "/volume1/circulation/data/subscriptions_latest.csv",
    "vacations_file": "/volume1/circulation/data/vacations_latest.csv",
    "rates_file": "/volume1/circulation/data/rates_latest.csv",
}

# Business unit mapping
BUSINESS_UNITS = {
    "TJ": ("South Carolina", "The Journal"),
    "TA": ("Michigan", "The Advertiser"),
    "TR": ("Wyoming", "The Ranger"),
    "LJ": ("Wyoming", "Lander Journal"),
    "WRN": ("Wyoming", "Wind River News"),
    "FN": ("Archived", "Fayette News (SOLD)"),
}


def connect_db():
    """Connect to MariaDB database"""
    try:
        conn = mysql.connector.connect(
            host=CONFIG["db_host"],
            port=CONFIG["db_port"],
            database=CONFIG["db_name"],
            user=CONFIG["db_user"],
            password=CONFIG["db_password"],
        )
        print(f"✓ Connected to database: {CONFIG['db_name']}")
        return conn
    except mysql.connector.Error as err:
        print(f"✗ Database connection failed: {err}")
        return None


def load_rate_mappings(rates_file):
    """Load rate ID to edition mappings"""
    rate_to_edition = {}

    if not os.path.exists(rates_file):
        print(f"✗ Rates file not found: {rates_file}")
        return rate_to_edition

    with open(rates_file, "r") as f:
        reader = csv.reader(f)
        header = next(reader)

        for row in reader:
            try:
                # Column 55 (0-indexed) = Sub Rate Id
                # Column 5 = Edition
                # Column 3 = Description
                rate_id = int(row[55])
                edition = row[5].strip()
                description = row[3].strip()
                rate_to_edition[rate_id] = {"edition": edition, "description": description}
            except (ValueError, IndexError):
                continue

    print(f"✓ Loaded {len(rate_to_edition)} rate mappings")
    return rate_to_edition


def load_vacations(vacations_file, today):
    """Load vacation holds and categorize"""
    vacation_by_sp_id = {}

    if not os.path.exists(vacations_file):
        print(f"✗ Vacations file not found: {vacations_file}")
        return vacation_by_sp_id

    with open(vacations_file, "r") as f:
        reader = csv.DictReader(f)

        for row in reader:
            sp_id = int(row["vd_sp_id"])
            beg_date = datetime.strptime(row["vd_beg_date"], "%m/%d/%y")
            end_date = datetime.strptime(row["vd_end_date"], "%m/%d/%y")

            vacation_by_sp_id[sp_id] = {
                "begin": beg_date,
                "end": end_date,
                "is_active": beg_date <= today <= end_date,
            }

    print(f"✓ Loaded {len(vacation_by_sp_id)} vacation records")
    return vacation_by_sp_id


def process_subscriptions(subscriptions_file, rate_mappings, vacations, today):
    """Process subscriptions and calculate metrics by paper"""

    if not os.path.exists(subscriptions_file):
        print(f"✗ Subscriptions file not found: {subscriptions_file}")
        return None

    # Initialize metrics by edition
    metrics = defaultdict(
        lambda: {
            "total_active": 0,
            "on_vacation": 0,
            "deliverable": 0,
            "mail": 0,
            "carrier": 0,
            "digital": 0,
            "rates": defaultdict(int),
        }
    )

    print("Processing subscriptions...")

    with open(subscriptions_file, "r") as f:
        reader = csv.DictReader(f)

        for row in reader:
            sp_num = row["sp_num"]
            rate_id = int(row["sp_rate_id"]) if row["sp_rate_id"] else 0
            route = row["sp_route"].upper()
            vac_ind = int(row["sp_vac_ind"]) if row["sp_vac_ind"] else 0

            # Get edition from rate
            if rate_id not in rate_mappings:
                continue

            edition = rate_mappings[rate_id]["edition"]
            rate_desc = rate_mappings[rate_id]["description"]

            # Update metrics
            metrics[edition]["total_active"] += 1
            metrics[edition]["rates"][rate_id] += 1

            # Check vacation status
            is_on_vacation = False
            if vac_ind > 0 and vac_ind in vacations:
                if vacations[vac_ind]["is_active"]:
                    metrics[edition]["on_vacation"] += 1
                    is_on_vacation = True

            if not is_on_vacation:
                metrics[edition]["deliverable"] += 1

            # Classify delivery type
            if route == "MAIL":
                metrics[edition]["mail"] += 1
            elif route in ("CARRIER", "MOTOR"):
                metrics[edition]["carrier"] += 1
            elif route == "INTERNET":
                metrics[edition]["digital"] += 1

    print(f"✓ Processed subscriptions for {len(metrics)} editions")
    return metrics


def save_to_database(conn, snapshot_date, metrics, vacations):
    """Save processed data to database"""
    cursor = conn.cursor()

    print(f"\nSaving snapshot for {snapshot_date}...")

    # Clear existing data for this date
    cursor.execute("DELETE FROM daily_snapshots WHERE snapshot_date = %s", (snapshot_date,))
    cursor.execute("DELETE FROM rate_distribution WHERE snapshot_date = %s", (snapshot_date,))

    # Insert daily snapshots
    for edition, data in metrics.items():
        if edition not in BUSINESS_UNITS:
            continue

        business_unit, paper_name = BUSINESS_UNITS[edition]

        cursor.execute(
            """
            INSERT INTO daily_snapshots
            (snapshot_date, paper_code, paper_name, business_unit,
             total_active, on_vacation, deliverable,
             mail_delivery, carrier_delivery, digital_only)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
        """,
            (
                snapshot_date,
                edition,
                paper_name,
                business_unit,
                data["total_active"],
                data["on_vacation"],
                data["deliverable"],
                data["mail"],
                data["carrier"],
                data["digital"],
            ),
        )

        # Insert top 10 rates for this paper
        sorted_rates = sorted(data["rates"].items(), key=lambda x: x[1], reverse=True)[:10]

        for rank, (rate_id, count) in enumerate(sorted_rates, 1):
            percentage = (count / data["total_active"] * 100) if data["total_active"] > 0 else 0

            cursor.execute(
                """
                INSERT INTO rate_distribution
                (snapshot_date, paper_code, rate_id, subscriber_count, percentage, rank_position)
                VALUES (%s, %s, %s, %s, %s, %s)
            """,
                (snapshot_date, edition, rate_id, count, percentage, rank),
            )

    conn.commit()
    print(f"✓ Saved snapshot to database")

    # Log successful import
    cursor.execute(
        """
        INSERT INTO import_log (file_type, file_name, records_processed, status)
        VALUES ('daily_snapshot', %s, %s, 'success')
    """,
        (snapshot_date.strftime("%Y-%m-%d"), sum(m["total_active"] for m in metrics.values())),
    )

    conn.commit()
    cursor.close()


def generate_summary_report(metrics):
    """Generate console summary report"""
    print("\n" + "=" * 80)
    print(f"CIRCULATION SNAPSHOT - {datetime.now().strftime('%Y-%m-%d %H:%M')}")
    print("=" * 80)

    # Calculate totals (excluding FN)
    total_active = sum(m["total_active"] for ed, m in metrics.items() if ed != "FN")
    total_vacation = sum(m["on_vacation"] for ed, m in metrics.items() if ed != "FN")
    total_deliverable = sum(m["deliverable"] for ed, m in metrics.items() if ed != "FN")

    print(f"\nOverall (Excluding FN):")
    print(f"  Total Active:     {total_active:6,}")
    print(f"  On Vacation:      {total_vacation:6,} ({total_vacation/total_active*100:5.2f}%)")
    print(f"  Deliverable:      {total_deliverable:6,}")

    print(f"\nBy Publication:")
    for edition in ["TJ", "TA", "TR", "LJ", "WRN", "FN"]:
        if edition not in metrics:
            continue

        data = metrics[edition]
        business_unit, paper_name = BUSINESS_UNITS[edition]
        status = " (SOLD)" if edition == "FN" else ""

        print(f"\n  {paper_name} ({edition}){status}")
        print(f"    Total:        {data['total_active']:5,}")
        print(f"    On Vacation:  {data['on_vacation']:5,}")
        print(f"    Deliverable:  {data['deliverable']:5,}")
        print(
            f"    Mail:         {data['mail']:5,} ({data['mail']/data['total_active']*100:5.1f}%)"
        )
        print(
            f"    Digital:      {data['digital']:5,} ({data['digital']/data['total_active']*100:5.1f}%)"
        )

    print("\n" + "=" * 80)


def main():
    """Main execution"""
    print("=" * 80)
    print("CIRCULATION DASHBOARD - DATA IMPORT")
    print("=" * 80)

    today = datetime.now().date()
    today_dt = datetime.combine(today, datetime.min.time())

    # Step 1: Connect to database
    conn = connect_db()
    if not conn:
        return

    # Step 2: Load reference data
    print("\nLoading reference data...")
    rate_mappings = load_rate_mappings(CONFIG["rates_file"])
    vacations = load_vacations(CONFIG["vacations_file"], today_dt)

    # Step 3: Process subscriptions
    metrics = process_subscriptions(
        CONFIG["subscriptions_file"], rate_mappings, vacations, today_dt
    )

    if not metrics:
        print("✗ Failed to process subscriptions")
        conn.close()
        return

    # Step 4: Save to database
    save_to_database(conn, today, metrics, vacations)

    # Step 5: Generate report
    generate_summary_report(metrics)

    # Close connection
    conn.close()
    print("\n✓ Import completed successfully!")


if __name__ == "__main__":
    main()
