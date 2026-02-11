from typing import List, Dict, Any


class Clusterer:
    """Aggregates findings into logical groups for reporting alignment.

    This utility classifies flat lists of findings into hierarchical buckets
    based on Category or Entity Type, facilitating structured reporting.
    """

    def __init__(self) -> None:
        pass

    def cluster_by_category(
        self, findings: List[Dict[str, Any]]
    ) -> Dict[str, List[Dict[str, Any]]]:
        """Group findings by their high-level Category (e.g., 'Data Leak', 'Thu').

        Args:
            findings: Flat list of finding dictionaries.

        Returns:
            Dictionary mapping Category names to lists of findings.
        """
        clusters: Dict[str, List[Dict[str, Any]]] = {}
        for finding in findings:
            cat = finding.get("category", "Uncategorized")
            if cat not in clusters:
                clusters[cat] = []
            clusters[cat].append(finding)
        return clusters

    def cluster_by_entity_type(
        self, findings: List[Dict[str, Any]]
    ) -> Dict[str, List[Dict[str, Any]]]:
        """Group findings by their specific Entity Type (e.g., 'Email', 'IP').

        Args:
            findings: Flat list of finding dictionaries.

        Returns:
            Dictionary mapping Entity Types to lists of findings.
        """
        clusters: Dict[str, List[Dict[str, Any]]] = {}
        for finding in findings:
            etype = finding.get("entity", "Unknown")
            if etype not in clusters:
                clusters[etype] = []
            clusters[etype].append(finding)
        return clusters
