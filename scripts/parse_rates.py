#!/usr/bin/env python3
"""
Parse rates.csv and generate market rate lookup table
Finds the maximum rate for each paper + subscription length combination
"""

import csv
import sys
from collections import defaultdict
from decimal import Decimal


def normalize_subscription_length(length, len_type):
    """
    Normalize subscription length to match subscriber_snapshots format
    Examples:
        1 + Y = 1 Y (1 year) - WITH SPACE
        12 + M = 12 M (12 months) - WITH SPACE
        52 + W = 52 W (52 weeks) - WITH SPACE
        1 + M = 1 M (1 month) - WITH SPACE

    Note: Must match the format in subscriber_snapshots table!
    """
    # Strip whitespace
    len_type = len_type.strip()
    length = str(length).strip()

    # Return format WITH SPACE to match subscriber_snapshots
    return f"{length} {len_type}"


def calculate_annualized_rate(rate, length, len_type):
    """
    Calculate annualized rate for comparison purposes
    """
    rate = Decimal(str(rate))
    length = int(length)

    if len_type == "Y":
        return rate
    elif len_type == "M":
        return rate * (Decimal("12") / Decimal(str(length)))
    elif len_type == "W":
        return rate * (Decimal("52") / Decimal(str(length)))
    elif len_type == "D":
        return rate * Decimal("365") / Decimal(str(length))
    else:
        return rate


def main():
    rates_file = "/Users/johncorbin/Desktop/projs/nwdownloads/queries/rates.csv"

    # Store max rate for each paper + subscription length
    # market_rates[paper_code][subscription_length] = {
    #     'rate': max_rate,
    #     'rate_name': description,
    #     'annualized': annualized_rate
    # }
    market_rates = defaultdict(
        lambda: defaultdict(
            lambda: {"rate": Decimal("0"), "rate_name": "", "annualized": Decimal("0")}
        )
    )

    with open(rates_file, "r") as f:
        reader = csv.DictReader(f)

        for row in reader:
            try:
                paper_code = row[" Rate.rr Edition"].strip()
                rate_name = row[" Rate.rr Online Desc"].strip()
                length = row[" Rate.rr Length"].strip()
                len_type = row[" Rate.rr Len Type(m=month,Y-year,W=week)"].strip()
                rate = Decimal(row[" Full Rate"].strip())

                # Skip $0 rates (comps, promos)
                if rate <= 0:
                    continue

                # Normalize subscription length
                sub_length = normalize_subscription_length(length, len_type)

                # Calculate annualized rate
                annualized = calculate_annualized_rate(rate, length, len_type)

                # Track maximum rate for this paper + subscription length
                current_max = market_rates[paper_code][sub_length]["rate"]
                if rate > current_max:
                    market_rates[paper_code][sub_length] = {
                        "rate": rate,
                        "rate_name": rate_name,
                        "annualized": annualized,
                    }

            except (KeyError, ValueError, decimal.InvalidOperation) as e:
                print(f"Warning: Skipping row due to error: {e}", file=sys.stderr)
                continue

    # Output SQL CREATE TABLE and INSERT statements
    print("-- Market Rate Structure Table")
    print("-- Contains the maximum rate for each paper + subscription length")
    print()
    print("DROP TABLE IF EXISTS rate_structure;")
    print()
    print("CREATE TABLE rate_structure (")
    print("    id INT AUTO_INCREMENT PRIMARY KEY,")
    print("    paper_code VARCHAR(10) NOT NULL,")
    print("    subscription_length VARCHAR(20) NOT NULL,")
    print("    market_rate DECIMAL(10,2) NOT NULL,")
    print("    rate_name VARCHAR(255) NULL,")
    print(
        "    annualized_rate DECIMAL(10,2) NOT NULL COMMENT 'Rate normalized to annual for comparison',"
    )
    print("    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,")
    print("    UNIQUE KEY unique_paper_length (paper_code, subscription_length),")
    print("    INDEX idx_paper_code (paper_code)")
    print(") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci")
    print("COMMENT='Market rate lookup table for legacy rate gap analysis';")
    print()
    print("-- Insert market rates")

    # Sort by paper_code then subscription_length for readability
    for paper_code in sorted(market_rates.keys()):
        for sub_length in sorted(market_rates[paper_code].keys()):
            data = market_rates[paper_code][sub_length]
            rate = data["rate"]
            rate_name = data["rate_name"].replace("'", "\\'")  # Escape quotes
            annualized = data["annualized"]

            print(
                f"INSERT INTO rate_structure (paper_code, subscription_length, market_rate, rate_name, annualized_rate)"
            )
            print(f"VALUES ('{paper_code}', '{sub_length}', {rate}, '{rate_name}', {annualized});")

    # Print summary statistics
    print()
    print("-- Summary:")
    print(f"-- Total papers: {len(market_rates)}")
    for paper in sorted(market_rates.keys()):
        print(f"-- {paper}: {len(market_rates[paper])} subscription lengths")


if __name__ == "__main__":
    import decimal

    main()
