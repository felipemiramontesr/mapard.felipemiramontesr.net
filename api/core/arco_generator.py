import os
from datetime import datetime

TEMPLATE_PATH = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
    "08_Templates",
    "arco_mx_template.md",
)
OUTPUT_DIR = os.path.join(
    os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "04_Data", "arco"
)


class ArcoGenerator:
    """Generates ARCO Rights request documents.

    This engine populates legal templates to facilitate the Exercise of
    Access, Rectification, Cancellation, or Opposition rights for the client
    against identified data holders.
    """

    def __init__(self) -> None:
        """Load the ARCO Markdown template."""
        with open(TEMPLATE_PATH, "r", encoding="utf-8") as f:
            self.template = f.read()

    def generate_arco(
        self, client_name: str, finding: dict, right_type: str = "CANCELACIÓN"
    ) -> str:
        """Generate a filled ARCO request document for a specific finding.

        Args:
            client_name: Name of the requester.
            finding: The finding dictionary containing data holder info.
            right_type: The type of ARCO right to exercise.

        Returns:
            The file path to the generated Markdown document.
        """
        responsible = finding.get("responsible_party", {})

        content = self.template.format(
            date=datetime.now().strftime("%Y-%m-%d"),
            location="Ciudad de México, México",
            responsible_name=responsible.get("name", "N/A"),
            responsible_address=responsible.get("address", "N/A"),
            client_name=client_name,
            client_contact="[Correo del Cliente]",
            rights_type=right_type,
            data_value=finding.get("value"),
            evidence_url=finding.get("url", "N/A"),
            detection_date=finding.get("captured_at"),
            description=f"Solicito la {right_type.lower()} de mis datos personales de su plataforma debido a...",
        )

        # Save Draft
        safe_value = (
            finding.get("value", "data").replace("/", "_").replace(":", "")[:20]
        )
        filename = f"ARCO_{right_type}_{safe_value}_{datetime.now().strftime('%Y%m%d%H%M%S')}.md"
        filepath = os.path.join(OUTPUT_DIR, filename)

        with open(filepath, "w", encoding="utf-8") as f:
            f.write(content)

        return filepath
