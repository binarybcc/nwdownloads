#!/usr/bin/env python3
"""
Find the 604 missing subscribers by identifying rate IDs that exist in subscriptions
but not in the rates export file.
"""

import csv

# Read all subscription rate IDs and counts
sub_rates = {}
with open('/Users/johncorbin/Desktop/projs/nwdownloads/queries/QueryBuilder_Missingsubs20251125142957.csv', 'r') as f:
    reader = csv.DictReader(f)
    for row in reader:
        rate_id = int(row['sp_rate_id'])
        count = int(row['count'])
        sub_rates[rate_id] = count

print(f"Total subscription rate IDs: {len(sub_rates)}")
print(f"Total subscriptions: {sum(sub_rates.values())}")

# Read all rate IDs from the rates export (Sub Rate Id column, which is column 56 / 0-indexed 55)
rates_file = "/Users/johncorbin/Desktop/projs/nwdownloads/queries/snwtable.csv"
export_rate_ids = set()

with open(rates_file, 'r') as f:
    reader = csv.reader(f)
    header = next(reader)  # Skip header

    for row in reader:
        try:
            # Sub Rate Id is column 56 (0-indexed 55)
            rate_id = int(row[55])
            export_rate_ids.add(rate_id)
        except (ValueError, IndexError):
            continue

print(f"\nTotal rate IDs in export file: {len(export_rate_ids)}")

# Find rate IDs that exist in subscriptions but NOT in the rates export
missing_rate_ids = set(sub_rates.keys()) - export_rate_ids

print(f"\n=== MISSING RATE IDs (subscriptions exist but no rate definition) ===\n")
print(f"Total missing rate IDs: {len(missing_rate_ids)}")

# Calculate how many subscribers are affected
missing_subscriber_count = 0
for rate_id in sorted(missing_rate_ids):
    count = sub_rates[rate_id]
    missing_subscriber_count += count
    print(f"Rate ID {rate_id}: {count} subscribers")

print(f"\n=== SUMMARY ===")
print(f"Total missing subscribers: {missing_subscriber_count}")
print(f"Expected missing: 604")
print(f"Match: {'YES' if missing_subscriber_count == 604 else 'NO'}")

# Also show which rate IDs DO exist in both
print(f"\n=== RATE IDs FOUND IN BOTH (sample) ===")
found_rate_ids = set(sub_rates.keys()) & export_rate_ids
print(f"Total rate IDs found in both: {len(found_rate_ids)}")
found_subscriber_count = sum(sub_rates[rid] for rid in found_rate_ids)
print(f"Total subscribers with valid rates: {found_subscriber_count}")
