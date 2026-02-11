from typing import Dict, Any, List

class ResponsibleResolver:
    """Identifying the legal entity responsible for a data leak.
    
    This module maps source names (e.g., 'Facebook') to their known 
    legal entities and contact information for ARCO rights execution.
    """

    def __init__(self) -> None:
        """Initialize the static database of known entities."""
        self.responsibles = {
            "Google": {
                "name": "Google México, S. de R.L. de C.V.",
                "address": "Montes Urales 445, Lomas de Chapultepec, CDMX",
                "email": "arco@google.com" # Placeholder
            },
            "LinkedIn": {
                "name": "LinkedIn Corporation",
                "address": "Sunnyvale, CA, USA (Representation in MX via Microsoft)",
                "email": "privacy@linkedin.com"
            },
            "Facebook": {
                "name": "Meta Platforms, Inc.",
                "address": "Menlo Park, CA",
                "email": "privacy@facebook.com"
            }
        }

    def resolve(self, finding: Dict[str, Any]) -> Dict[str, Any]:
        """Enrich a finding with responsible party details.
        
        Args:
            finding: The finding dictionary.
            
        Returns:
            The finding dictionary enriched with 'responsible_party'.
        """
        source = finding.get('source_name')
        # Simple lookup
        responsible = self.responsibles.get(source, {
            "name": "Unknown Entity",
            "address": "Unknown Address",
            "email": "contact@domain.com"
        })
        
        finding['responsible_party'] = responsible
        return finding

    def resolve_findings(self, findings: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Batch process findings to resolve responsible parties.
        
        Args:
            findings: List of finding dictionaries.
            
        Returns:
            List of enriched findings.
        """
        resolved = []
        for f in findings:
            resolved.append(self.resolve(f))
        return resolved
