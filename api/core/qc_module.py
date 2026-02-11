import os

from typing import Dict, Any


class QCModule:
    """Implements specific checking logic for artifact validation.

    Contains the concrete checklist rules used to validate report integrity,
    file existence, and content completeness.
    """

    def __init__(self, reports_dir: str, arco_root: str) -> None:
        """Initialize with infrastructure paths."""
        self.reports_dir = reports_dir
        self.arco_root = arco_root

    def validate_report(
        self, base_name: str, client_name: str, client_id: str
    ) -> Dict[str, Any]:
        """Run the standard MAPA-RD v2.3 Validation Checklist.

        Args:
            base_name: The base filename of the report artifact.
            client_name: The expected client name in the report.
            client_id: The expected client ID.

        Returns:
            Dictionary containing 'passed' boolean and detailed 'checks' map.
        1. Idioma español (implicit in template)
        2. Lenguaje no técnico (implicit in template/logic)
        3. Secciones completas
        4. Anexos incluidos
        5. Nombres de archivos correctos
        6. IDs correctos
        7. PDF abre correctamente (file exists check)
        8. ARCO generados si aplican
        """
        results = {"passed": True, "checks": {}}

        # 1. Existence check
        pdf_path = os.path.join(self.reports_dir, f"{base_name}.pdf")
        md_path = os.path.join(self.reports_dir, f"{base_name}.md")

        # Consistent nomenclature for DATOS_TECNICOS
        json_base_name = base_name.replace(" - REPORTE - ", " - DATOS_TECNICOS - ")
        json_path = os.path.join(self.reports_dir, f"{json_base_name}.json")

        check_files = (
            os.path.exists(pdf_path)
            and os.path.exists(md_path)
            and os.path.exists(json_path)
        )
        results["checks"]["files_exist"] = check_files
        if not check_files:
            results["passed"] = False

        # 2. Content Validation (MD)
        if os.path.exists(md_path):
            with open(md_path, "r", encoding="utf-8") as f:
                content = f.read()

            # Check for sections
            required_sections = [
                "1. Resumen Ejecutivo",
                "2. Amenazas Reales Detectadas",
                "3. Plan de Acción Consolidado",
                "4. Gestión de Privacidad y Derechos ARCO",
                "5. Gestión Telefónica",
                "6. Conclusión",
                "7. Anexos: Solicitudes de Derechos ARCO",
                "8. Nota de Anexo Técnico",
            ]
            missing_sections = [s for s in required_sections if s not in content]
            results["checks"]["sections_complete"] = len(missing_sections) == 0
            if missing_sections:
                results["passed"] = False
                results["checks"]["missing_sections"] = missing_sections

            # Check IDs and Names in content
            id_match = str(client_id) in content
            name_match = (
                client_name in content
            )  # Might need normalization but checking raw first

            results["checks"]["id_correct"] = id_match
            results["checks"]["name_correct"] = name_match
            if not id_match or not name_match:
                results["passed"] = False

        # 3. ARCO Consistency
        arco_dir = os.path.join(self.arco_root, base_name)
        if os.path.exists(arco_dir):
            pdfs = [f for f in os.listdir(arco_dir) if f.endswith(".pdf")]
            guia_exists = any("ARCO_GUIA" in f for f in pdfs)
            results["checks"]["arco_guia_exists"] = guia_exists
            if not guia_exists and len(pdfs) > 0:
                results["passed"] = False

        return results
