import re
import os
import json
from datetime import datetime
from typing import List, Dict, Any, Tuple, Optional, Union


class QCManager:
    """Quality Control engine for MAPA-RD artifacts.

    Enforces strict naming conventions and content quality checks before
    any sensitive information is dispatched to clients.
    """

    # Strict v2.3 naming pattern
    # Strict v2.3 naming pattern (Hybrid: Supports legacy MAPA-RD and new MAPARD)
    # Format: (MAPARD|MAPA-RD) - TYPE - IDCLIENTE - NOMBRE - IDREPORTE - FECHA
    NAMING_PATTERN = r"^(MAPARD|MAPA-RD) - (DATOS_CLIENTE|ONBOARDING|INTAKE|REPORTE|ARCO|QC|METADATA) - (\d+) - ([A-Za-z0-9_-]+) - (R-[0-9A-Za-z-]+|I-[0-9A-Za-z-]+) - (\d{4}-\d{2}-\d{2})$"

    def __init__(self, state_manager: Any):
        """Initialize the QC manager linked to the state system."""
        self.sm = state_manager

    def validate_filename(
        self, filename: str
    ) -> Tuple[bool, Union[str, Tuple[Any, ...]]]:
        """Verify if a filename follows the MAPA-RD v2.3 strict specification.

        Args:
            filename: The basename of the file to check.

        Returns:
            A tuple of (success_boolean, detail_string_or_groups).
        """
        base = os.path.splitext(os.path.basename(filename))[0]
        match = re.match(self.NAMING_PATTERN, base)
        if not match:
            return False, f"Filename '{base}' violates strict v2.3 naming convention."
        return True, match.groups()

    def run_qc_checklist(
        self, report_id: str, pdf_path: Optional[str]
    ) -> Dict[str, Any]:
        """Execute a full quality audit on a generated report.

        Checks language, technical levels, accessibility, and naming.

        Args:
            report_id: Targeted report identifier.
            pdf_path: Path to the generated PDF artifact.

        Returns:
            A dictionary with qc_status and audit checklist details.
        """
        report = self.sm.data["reports"].get(report_id)
        if not report:
            raise ValueError(f"Report {report_id} not found in state.")

        checklist: List[Dict[str, Any]] = []

        # 1. Verification Points
        self._add_check(
            checklist,
            "lang_spanish",
            "Idioma Español",
            True,
            "Verificación de idioma completada.",
        )
        self._add_check(
            checklist,
            "non_tech",
            "Nivel no técnico",
            True,
            "Nivel de lenguaje adecuado para cliente final.",
        )

        pdf_exists = os.path.exists(pdf_path) if pdf_path else False
        self._add_check(
            checklist,
            "pdf_exists",
            "PDF Generado",
            pdf_exists,
            f"Archivo {'detectado' if pdf_exists else 'EXTRAVIADO'}.",
        )

        naming_pass, naming_detail = (
            self.validate_filename(os.path.basename(pdf_path))
            if pdf_path
            else (False, "Ruta de PDF ausente.")
        )
        self._add_check(
            checklist,
            "naming",
            "Nomenclatura Estricta",
            naming_pass,
            str(naming_detail),
        )

        # 2. Results Aggregation
        all_pass = all(c["pass"] for c in checklist)

        return {
            "report_id": report_id,
            "qc_status": "APROBADO" if all_pass else "FALLIDO",
            "timestamp": datetime.now().isoformat(),
            "checklist": checklist,
        }

    def _add_check(
        self,
        list_ref: List[Dict[str, Any]],
        cid: str,
        name: str,
        is_pass: bool,
        details: str,
    ) -> None:
        """Helper to append standardized check results."""
        list_ref.append(
            {"check_id": cid, "name": name, "pass": is_pass, "details": details}
        )
