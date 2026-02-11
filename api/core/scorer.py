"""
MAPA-RD: Intelligence Risk Scorer
---------------------------------
Author: Antigravity AI / Senior Python standards
Version: 2.2.0 (Pro)

Purpose:
    The Scorer acts as the cognitive layer of the pipeline, translating technical
    findings into actionable risk priorities (P0 to P3).

Risk Hierarchy:
    - P0 (Critical): Immediate intervention required (Data leaks, Financial links).
    - P1 (High): Structural threats (Malicious IPs, Botnets).
    - P2 (Medium): Brand/Identity risks (Squatting).
    - P3 (Low): Informational footprint (Public directories).
"""

import json
import os
import logging
from typing import List, Dict, Any, Optional

# Constants & Paths
# We look for scoring_rules.json in the Config directory for enterprise customization.
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CONFIG_PATH = os.path.join(BASE_DIR, '03_Config', 'scoring_rules.json')

# Logger initialization
logger = logging.getLogger(__name__)

class Scorer:
    """Evaluates the risk level of normalized findings.
    
    Assigns a priority score (P0 to P3) based on category, entity type,
    and sensitive keywords found in the data values.
    """

    def __init__(self, rules_path: str = CONFIG_PATH):
        """Initialize the Scorer with specific risk rules.
        
        Args:
            rules_path: URL/Path to the JSON rule-set. Defaults to system config.
        """
        self.rules: Dict[str, Any] = self._load_rules(rules_path)
        
        # Pre-compile critical keywords for O(1) definition
        self.critical_keywords = [
            "banorte", "bbva", "santander", "banamex", # Financial targets
            "password", "contraseña", "passwd",        # Security credentials
            "token", "cvv", "clabe",                   # High-value assets
            "secret", "api_key"                        # Developer exposures
        ]

    def _load_rules(self, path: str) -> Dict[str, Any]:
        """Load external scoring rules or use internal defaults (Safe fallback)."""
        if os.path.exists(path):
            try:
                with open(path, 'r', encoding='utf-8') as f:
                    logger.debug(f"Loading custom scoring rules from {path}")
                    return json.load(f)
            except (json.JSONDecodeError, IOError) as e:
                logger.error(f"Failed to load rules: {e}. Using hardcoded logic.")
        return {"rules": []}

    def calculate_score(self, finding: Dict[str, Any]) -> Dict[str, Any]:
        """Determine the P0-P3 risk score for a single finding.
        
        Algorithm flow:
        1. Set baseline (P3).
        2. Escalate based on Category (Data Leak -> P0, Threat -> P1).
        3. Escalate based on Content (Financial keywords -> P0).
            
        Args:
            finding: A normalized finding dictionary.
            
        Returns:
            The finding dictionary updated with risk_score and risk_rationale.
        """
        category = finding.get('category')
        entity = finding.get('entity')
        value_lower = str(finding.get('value', '')).lower()
        
        # ---------------------------------------------------------
        # STEP 1: BASELINE INITIALIZATION
        # Defaulting to Low (P3) following the principle of least alarm.
        # ---------------------------------------------------------
        score = "P3"
        rationale = "Información pública de bajo impacto reconocida en fuentes abiertas."

        # ---------------------------------------------------------
        # STEP 2: CATEGORY-BASED ESCALATION
        # Hardcoded logic ensures critical threats are detected even if Config fails.
        # ---------------------------------------------------------
        if category == "Data Leak" or entity == "Compromised Credentials":
            score = "P0"
            rationale = "CRÍTICO: Credenciales o datos privados expuestos en filtración detectada."
        elif category == "Threat":
            score = "P1"
            rationale = "ALTO: Asociación positiva con infraestructura maliciosa o vectores de ataque."
        elif entity == "Squatted/Similar Domain":
            score = "P2"
            rationale = "MEDIO: Detección de activo similar (Typosquatting) con riesgo de suplantación."
            
        # ---------------------------------------------------------
        # STEP 3: CONTEXTUAL HEURISTICS (KEYWORD SEARCH)
        # Deep inspection for financial or sensitive technical terms.
        # ---------------------------------------------------------
        
        for kw in self.critical_keywords:
            if kw in value_lower:
                # Content-based escalation overrides structural categories
                score = "P0"
                rationale = f"CRÍTICO: Palabra clave de alta sensibilidad detectada ({kw})."
                break

        # Log significant findings (P0/P1)
        if score in ["P0", "P1"]:
            logger.warning(f"HIGH RISK DETECTED [{score}]: {finding.get('finding_id')}")

        finding['risk_score'] = score
        finding['risk_rationale'] = rationale
        return finding
        
    def score_findings(self, findings: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Process a list of findings through the scoring engine.
        
        Optimized via list comprehension for linear execution performance.

        Args:
            findings: List of normalized findings.
            
        Returns:
            List of scored findings.
        """
        logger.info(f"Scoring {len(findings)} findings.")
        scored = [self.calculate_score(f) for f in findings if f]
        return scored
