"""
MAPA-RD: Intelligence Data Deduplicator
---------------------------------------
Author: Antigravity AI / Senior Python standards
Version: 2.1.0 (Pro)

Purpose:
    Ensures data integrity by filtering out redundant events captured during
    multi-source intelligence gathering.

Technique:
    Uses a 'Seen IDs' set for O(1) lookup time, resulting in O(n) total
    complexity. This is the gold standard for large-scale OSINT datasets.
"""

import logging
from typing import List, Dict, Any, Set

# Local logger setup
logger = logging.getLogger(__name__)


class Deduper:
    """Removes redundant information from scanned data.

    Ensures that each unique indicator is only processed once in the pipeline,
    preventing duplicate alerts and cleaning up the final report.
    """

    def deduplicate(self, findings: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Filter out findings with duplicate IDs or equivalent semantic values.

        This method preserves the original order of findings (First-seen wins),
        but now also handles cross-module redundancy (e.g. DOMAIN_NAME vs INTERNET_NAME).

        Args:
            findings: A list of normalized finding dictionaries.

        Returns:
            A list containing only the first occurrence of each unique information piece.
        """
        # ---------------------------------------------------------
        # INITIALIZATION
        # seen_ids: Standard exact duplicate check.
        # seen_values: Value-based check for "identity" types.
        # ---------------------------------------------------------
        seen_ids: Set[str] = set()
        seen_values: Set[str] = set()
        unique_findings: List[Dict[str, Any]] = []

        # Types that represent the same concept (Host/Domain)
        # Should be treated as redundant if the VALUE is identical.
        EQUIVALENT_TYPES = {
            "DOMAIN_NAME",
            "INTERNET_NAME",
            "SIMILARDOMAIN",
            "AFFILIATE_IPADDR",
        }

        initial_count = len(findings)
        logger.debug(f"Starting deduplication of {initial_count} items.")

        # ---------------------------------------------------------
        # PROCESSING LOOP
        # ---------------------------------------------------------
        for finding in findings:
            fid = finding.get("finding_id")
            etype = finding.get("event_type")
            val = (
                str(finding.get("value", "")).lower().strip()
            )  # Normalize value for comparison

            # 1. Exact Match Check (ID)
            if fid in seen_ids:
                continue

            # 2. Semantic Value Check (Cross-Type)
            if etype in EQUIVALENT_TYPES:
                if val in seen_values:
                    # Logically redundant (same domain from different module/type)
                    continue
                seen_values.add(val)

            # If passed checks, keep it.
            seen_ids.add(fid)
            unique_findings.append(finding)

        final_count = len(unique_findings)
        logger.info(
            f"Deduplication finished. Removed {initial_count - final_count} redundant findings."
        )

        return unique_findings
