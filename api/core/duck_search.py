"""
MAPA-RD: DuckDuckGo OSINT Search Module
---------------------------------------
Author: Antigravity AI / Senior Python standards
Version: 1.0.0 (Pro)

Purpose:
    Provides a legal, free, and robust method for gathering public web
    intelligence using DuckDuckGo.
"""

import logging
from typing import List, Dict, Any
from duckduckgo_search import DDGS

logger = logging.getLogger(__name__)


class DuckSearch:
    """Handles OSINT searches via DuckDuckGo."""

    def __init__(self):
        pass

    def search(self, query: str, max_results: int = 5) -> List[Dict[str, Any]]:
        """
        Performs a web search and returns normalized results.

        Args:
            query: The search term (e.g., an email or name).
            max_results: Maximum number of links to retrieve.

        Returns:
            A list of standardized finding dictionaries.
        """
        logger.info(f"Executing DDG OSINT search for: {query}")
        findings = []

        try:
            with DDGS() as ddgs:
                results = list(ddgs.text(query, max_results=max_results))

            for res in results:
                finding = {
                    "finding_id": f"DDG-{hash(res['href']) % 1000000}",  # Simple deterministic ID
                    "category": "Web Mention",
                    "entity": "Public Record / Web Disclosure",
                    "value": res["href"],
                    "title": res.get("title", "No Title"),
                    "snippet": res.get("body", "No snippet available"),
                    "risk_score": "P3",  # Web mentions are generally low priority unless confirmed leak
                    "risk_rationale": f"Mención pública detectada en DuckDuckGo: {res.get('title')}",
                }
                findings.append(finding)

            logger.info(f"DDG search completed. Found {len(findings)} results.")

        except Exception as e:
            logger.error(f"DDG Search failed: {str(e)}")

        return findings


if __name__ == "__main__":
    # Test execution
    ds = DuckSearch()
    results = ds.search("felipe@example.com")
    for r in results:
        print(f"[{r['category']}] {r['title']} -> {r['value']}")
