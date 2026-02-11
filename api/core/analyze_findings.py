"""
MAPA-RD: Raw Data Analysis Utility
----------------------------------
Purpose:
    This script is a diagnostic tool used to manually inspect raw SpiderFoot 
    JSON output. It analyzes event type distributions and scans for specific 
    critical keywords (e.g., 'leak', 'password') to validate findings 
    before full pipeline ingestion.

Usage:
    Run standalone: python analyze_findings.py
"""

import json
from collections import Counter
import os

# Define the absolute path to the raw data file for inspection
# NOTE: This path is hardcoded for debugging specific cases.
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RAW_FILE = os.path.join(BASE_DIR, '04_Data', 'raw', 'felipe-de-jesus-miramontes-romero', 'bb54e9c2-835c-4333-ba92-aaee976062ae', 'spiderfoot.json')

with open(RAW_FILE, 'r', encoding='utf-8') as f:
    data = json.load(f)

types = Counter([item.get('type') for item in data])
print("\n--- Event Type Distribution ---")
for t, count in types.most_common():
    print(f"{t}: {count}")

print("\n--- Sample Critical Patterns (Searching for leaks/mentions) ---")
keywords = ["leak", "breach", "password", "banorte", "account", "pwned"]
for item in data:
    content = str(item.get('data', '')).lower()
    for kw in keywords:
        if kw in content:
            print(f"FOUND [{kw}] in Type {item.get('type')}: {item.get('data')[:100]}...")
            break
