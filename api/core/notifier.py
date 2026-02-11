import smtplib
import json
import os
import time
from datetime import datetime
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.mime.base import MIMEBase
from email import encoders, utils
from typing import List, Tuple, Optional, Dict, Any

from config_manager import ConfigManager


class Notifier:
    """Handle secure email notifications for MAPA-RD reports.

    This class supports multiple backends (SMTP, Stub) and includes
    retry logic, attachment handling, and professional templates.
    """

    def __init__(self, config_dict: Optional[Dict[str, Any]] = None) -> None:
        """Initialize the Notifier with configuration.

        Args:
            config_dict: Optional dictionary to override file-based config (useful for tests).
        """
        self.cm = ConfigManager()
        # Load email section from ConfigManager
        file_config = self.cm.get_section("email")
        self.config: Dict[str, Any] = file_config.copy()

        # Override with passed dict (High priority for tests)
        if config_dict:
            self.config.update(config_dict)

        # Environment Variables (Highest priority)
        self._apply_env_overrides()

        # Resolve Settings
        self.backend = (
            self.config.get("backend")
            or self.config.get("notification_backend")
            or "stub"
        ).lower()

        self.sender = self.config.get("sender_email", "noreply@mapa-rd.com")
        self.override_to = self.config.get("to_override")

        # Infrastructure paths
        self.outbox_dir = os.path.join(self.cm.project_root, "04_Data", "outbox")

        if self.backend == "stub":
            os.makedirs(self.outbox_dir, exist_ok=True)
        elif self.backend == "smtp":
            self._validate_smtp_config()

    def _apply_env_overrides(self) -> None:
        """Map environment variables to internal config keys."""
        env_map = {
            "EMAIL_BACKEND": "backend",
            "SMTP_HOST": "smtp_host",
            "SMTP_PORT": "smtp_port",
            "SMTP_USER": "smtp_user",
            "SMTP_PASS": "smtp_pass",
            "SMTP_TLS": "smtp_tls",
            "SMTP_FROM": "sender_email",
        }
        for env_key, conf_key in env_map.items():
            val = os.getenv(env_key)
            if val:
                self.config[conf_key] = val

    def _validate_smtp_config(self) -> None:
        """Ensure all required SMTP credentials are present."""
        required = ["smtp_host", "smtp_port", "smtp_user", "smtp_pass"]
        missing = [k for k in required if not self.config.get(k)]
        if missing:
            raise ValueError(
                f"SMTP Backend selected but missing required config: {missing}"
            )

    def send_report(
        self,
        receiver_emails: List[str],
        report_path: Optional[str],
        client_name: str,
        scan_id: Optional[str] = None,
        subject: Optional[str] = None,
        body: Optional[str] = None,
    ) -> Tuple[bool, str]:
        """Send the intelligence report via the configured backend.

        Args:
            receiver_emails: List of recipient addresses.
            report_path: Path to the PDF file to attach.
            client_name: Name of the client for the template.
            scan_id: Unique identifier for the scan.
            subject: Optional custom subject line.
            body: Optional custom body text.

        Returns:
            A tuple of (success_boolean, message_id_or_error).
        """
        start_time = time.time()

        # 1. Routing & Safety
        if isinstance(receiver_emails, str):
            receiver_emails = [receiver_emails]

        final_to = receiver_emails
        if self.override_to:
            print(
                f"[LOG] EMAIL_ROUTING | override=active | to_effective={self.override_to}"
            )
            final_to = [self.override_to]

        # 2. Build Message
        msg = self._create_message(final_to, client_name, scan_id, subject, body)

        # 3. Attach File
        if report_path:
            if not os.path.exists(report_path):
                return False, f"Attachment not found: {report_path}"
            self._attach_file(msg, report_path)

        # 4. Dispatch
        try:
            print(
                f"[LOG] EMAIL_SEND_ATTEMPT | report_id={scan_id} | backend={self.backend}"
            )

            if self.backend == "stub":
                result_id = self._send_stub(msg, client_name, scan_id or "N/A")
            else:
                result_id = self._send_smtp(msg, scan_id)

            duration = int((time.time() - start_time) * 1000)
            print(
                f"[LOG] EMAIL_SEND_SUCCESS | report_id={scan_id} | duration_ms={duration}"
            )
            return True, result_id

        except Exception as e:
            duration = int((time.time() - start_time) * 1000)
            err_msg = str(e)[:200]
            print(f"[LOG] EMAIL_SEND_FAIL | err={type(e).__name__} | msg={err_msg}")
            return False, err_msg

    def _create_message(
        self,
        to: List[str],
        client_name: str,
        scan_id: Optional[str],
        subject: Optional[str],
        body: Optional[str],
    ) -> MIMEMultipart:
        """Construct the MIME email structure."""
        msg = MIMEMultipart()
        msg["From"] = f"MAPA-RD <{self.sender}>"
        msg["To"] = ", ".join(to)
        msg["Reply-To"] = self.sender

        report_num = scan_id.split("-")[-1] if scan_id and "R-" in scan_id else "000"
        msg["Subject"] = subject or f"Reporte MAPA-RD #{report_num} | {client_name}"
        msg["Date"] = utils.formatdate(localtime=True)

        # Deliverability Header
        domain = self.sender.split("@")[-1] if "@" in self.sender else None
        msg["Message-ID"] = utils.make_msgid(domain=domain)

        # Body Template
        date_str = datetime.now().strftime("%d/%m/%Y")

        # Body Container (Alternative: Text vs HTML)
        body_part = MIMEMultipart("alternative")

        # 1. Plain Text Fallback
        text_content = body or (
            f"Estimado(a) {client_name},\n\n"
            f"Adjunto encontrará su Reporte de Inteligencia MAPA-RD ({date_str}).\n"
            f"ID de Reporte: {scan_id or 'N/A'}\n\n"
            "Atentamente,\nEl Equipo de MAPA-RD"
        )
        body_part.attach(MIMEText(text_content, "plain", "utf-8"))

        # 2. HTML Version (Premium)
        try:
            template_path = os.path.join(
                os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                "08_Templates",
                "email_notification.html",
            )
            if os.path.exists(template_path):
                with open(template_path, "r", encoding="utf-8") as f:
                    html_template = f.read()

                # Simple replacement
                html_content = (
                    html_template.replace("{client_name}", client_name)
                    .replace("{scan_id}", scan_id or "N/A")
                    .replace("{date_str}", date_str)
                    .replace("{year}", datetime.now().strftime("%Y"))
                )

                body_part.attach(MIMEText(html_content, "html", "utf-8"))
            else:
                print(
                    f"[LOG] HTML Template not found at {template_path}. using text only."
                )
        except Exception as e:
            print(f"[!] HTML rendering failed: {e}. using text only.")

        # Attach the Body container to the Main (Mixed) container
        msg.attach(body_part)

        return msg

    def _attach_file(self, msg: MIMEMultipart, path: str) -> None:
        """Safely attach a binary file to the email."""
        with open(path, "rb") as f:
            part = MIMEBase("application", "octet-stream")
            part.set_payload(f.read())
            encoders.encode_base64(part)
            part.add_header(
                "Content-Disposition", f"attachment; filename={os.path.basename(path)}"
            )
            msg.attach(part)

    def _send_stub(self, msg: MIMEMultipart, client_name: str, scan_id: str) -> str:
        """Simulate sending by writing a JSON file to the outbox."""
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"{timestamp}_{scan_id}.json"
        filepath = os.path.join(self.outbox_dir, filename)

        data = {
            "to": msg["To"],
            "subject": msg["Subject"],
            "message_id": msg["Message-ID"],
            "client": client_name,
            "timestamp": datetime.now().isoformat(),
        }
        with open(filepath, "w", encoding="utf-8") as f:
            json.dump(data, f, indent=4)
        return msg["Message-ID"] or "stub-id"

    def _send_smtp(self, msg: MIMEMultipart, scan_id: Optional[str]) -> str:
        """Execute real SMTP transmission with retry logic."""
        host = self.config["smtp_host"]
        port = int(self.config["smtp_port"])
        user = self.config["smtp_user"]
        pwd = self.config["smtp_pass"]
        use_tls = str(self.config.get("smtp_tls", "true")).lower() == "true"

        for attempt in range(1, 4):
            try:
                server_cls = smtplib.SMTP_SSL if port == 465 else smtplib.SMTP
                with server_cls(host, port, timeout=30) as server:
                    if port != 465 and use_tls:
                        server.starttls()
                    if user and pwd:
                        server.login(user, pwd)
                    server.send_message(msg)
                return msg["Message-ID"]
            except Exception as e:
                if attempt == 3:
                    raise e
                time.sleep(2**attempt)
        return "error"
