import os
import json
import subprocess
import requests
import time
from datetime import datetime
from typing import List, Dict, Any, Tuple, Optional

# Internal Core Modules
from config_manager import ConfigManager
from state_manager import StateManager
from qc_manager import QCManager
from normalizer import Normalizer
from deduper import Deduper
from scorer import Scorer
from report_generator import ReportGenerator
from notifier import Notifier
from duck_search import DuckSearch

class Orchestrator:
    """The central brain of MAPA-RD.
    
    Coordinates data collection (SpiderFoot), processing (Normalized/Scorer),
    artifact generation, quality control gates, and notification dispatch.
    """

    def __init__(self) -> None:
        """Initialize core components and load environmental configurations.
        
        This constructor initializes:
        - StateManager: For persistence.
        - QCManager: For quality control checks.
        - ReportGenerator: For HTML/PDF creation.
        - Notifier: For email dispatch.
        """
        self.sm: StateManager = StateManager()
        self.qc: QCManager = QCManager(self.sm)
        self.rg: ReportGenerator = ReportGenerator(self.sm)
        self.notifier: Notifier = Notifier()
        
        self.cm = ConfigManager()
        self.ds = DuckSearch()  # New DDG Engine
        self.spiderfoot_path = self.cm.get("spiderfoot_path", ".")
        self.python_exe = self.cm.get("python_exe", "python")
        
        self.sf_script = os.path.join(self.spiderfoot_path, "sf.py")

    # _load_config method removed as it is efficiently handled by ConfigManager

    def run_spiderfoot_scan(self, target: str, scan_id: str) -> List[Dict[str, Any]]:
        """Execute a SpiderFoot CLI scan or fallback to simulation.
        
        Args:
            target: Domain or email to scan.
            scan_id: Identifier for logging.
            
        Returns:
            A list of SpiderFoot event dictionaries.
        """
        if not os.path.exists(self.sf_script):
            print(f"[!] CRITICAL: SpiderFoot not found at {self.sf_script}. Cannot proceed with scan.")
            return []

        modules = self.cm.get("spiderfoot_core_modules", [])
        module_flag = ["-m", ",".join(modules)] if modules else []

        cmd = [self.python_exe, self.sf_script, "-s", target, "-o", "json", "-q"] + module_flag
        print(f"[*] Executing SpiderFoot (Core OSINT): {' '.join(cmd)}")
        
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, encoding='utf-8', timeout=600) # nosec
            if result.returncode != 0:
                print(f"[!] CLI Error: {result.stderr}")
                return []
            
            events = []
            for line in result.stdout.splitlines():
                if line.strip():
                    try:
                        events.append(json.loads(line))
                    except json.JSONDecodeError:
                        continue
            return events
        except (subprocess.SubprocessError, Exception) as e:
            print(f"[!] Scan Exception: {e}")
            return []

    def run_hibp_scan(self, email: str) -> List[Dict[str, Any]]:
        """Fetch breaches for a target email using HIBP API.
        
        Args:
            email: Target email address.
            
        Returns:
            List of breach dictionaries.
        """
        api_key = self.cm.get("hibp_api_key", "")
        if not api_key:
            print("[!] HIBP API Key missing in config. Skipping HIBP scan.")
            return []

        url = f"https://haveibeenpwned.com/api/v3/breachedaccount/{email}?truncateResponse=false"
        headers = {
            "hibp-api-key": api_key,
            "user-agent": "MAPA-RD-Orchestrator"
        }
        
        print(f"[*] Fetching HIBP breaches for {email}...")
        try:
            response = requests.get(url, headers=headers, timeout=10)
            if response.status_code == 200:
                return response.json()
            elif response.status_code == 404:
                return []
            elif response.status_code == 429:
                print("[!] HIBP Rate limit hit. Waiting 10s...")
                time.sleep(10)
                return self.run_hibp_scan(email)
            else:
                print(f"[!] HIBP Error: {response.status_code}")
                return []
        except Exception as e:
            print(f"[!] HIBP Exception: {e}")
            return []

    def run_osint_scan(self, target: str) -> List[Dict[str, Any]]:
        """Performs public web intelligence search via DuckDuckGo.
        
        Args:
            target: The search query (email, domain, name).
            
        Returns:
            List of normalized web findings.
        """
        print(f"[*] Running DuckDuckGo OSINT search for {target}...")
        return self.ds.search(target)

    def orchestrate(self, client_id: str, analysis_type: str = "monthly") -> Tuple[str, str]:
        """Ad-hoc entry point for CLI-driven scans.
        
        Args:
            client_id: Target client identifier.
            analysis_type: Report frequency/type.
            
        Returns:
            A tuple of (intake_id, client_directory).
        """
        intake_type = analysis_type.upper() if analysis_type else "ON_DEMAND"
        
        # Ensure client exists in state
        if not self.sm.get_client(client_id):
             self.sm.create_client(client_id, client_id)

        intake_id = self.sm.create_intake(client_id, intake_type, requested_by="CLI_USER")
        
        # Populate identity from client record for HIBP scan
        client = self.sm.get_client(client_id)
        if client and client.get("email"):
            self.sm.data["intakes"][intake_id]["identity"] = {
                "emails": [client["email"]]
            }

        self.sm.update_intake(intake_id, "AUTORIZADO", actor="CLI_USER")
        
        self.execute_pipeline(intake_id)
        
        client = self.sm.get_client(client_id)
        client_dir = client.get("client_dir", client_id) if client else client_id
        return intake_id, client_dir

    def execute_pipeline(self, intake_id: str) -> None:
        """Run the full intake-to-report lifecycle.
        
        Args:
            intake_id: The ID of the authorized intake to process.
        """
        intake = self.sm.data["intakes"].get(intake_id)
        if not intake:
            return
        
        client_id = intake["client_id"]
        client = self.sm.get_client(client_id)
        if not client:
             return
        
        print(f"\n[PIPELINE START] {intake_id} | Client: {client['client_name_full']}")

        # 1. Start execution
        self.sm.update_intake(intake_id, "EJECUTADO")
        
        # 2. Intel Gathering (SpiderFoot)
        target = self._resolve_target(intake, client)
        raw_findings = self.run_spiderfoot_scan(target, intake_id)
        self._persist_raw_data(client["client_dir"], intake_id, raw_findings)
        
        # 3. Processing (Normalize, Deduplicate, Score)
        print("[*] Processing intelligence...")
        norm = Normalizer().normalize_scan(raw_findings)
        deduped = Deduper().deduplicate(norm)
        scored = Scorer().score_findings(deduped)
        
        # 4. HIBP Enrichment
        targets = intake.get("identity", {}).get("emails", [])
        hibp_data = []
        for email in targets:
            breaches = self.run_hibp_scan(email)
            for b in breaches:
                # Basic normalization for HIBP
                finding = {
                    "finding_id": f"HIBP-{b['Name']}",
                    "category": "Data Leak",
                    "entity": "Compromised Credentials",
                    "value": email,
                    "breach_title": b['Name'],
                    "breach_classes": b['DataClasses'],
                    "breach_date": b['BreachDate'],
                    "breach_desc": b['Description'],
                    "risk_score": "P0" if "Passwords" in b['DataClasses'] else "P1",
                    "risk_rationale": f"Filtración confirmada en {b['Name']}. Clases: {', '.join(b['DataClasses'])}"
                }
                hibp_data.append(finding)
            # Avoid rate limits
            if len(targets) > 1: time.sleep(1.5)
        
        # 5. DuckDuckGo OSINT Enrichment
        osint_data = self.run_osint_scan(target)

        # 6. Consolidation
        scored.extend(hibp_data)
        scored.extend(osint_data)
        
        # 7. Artifact Generation
        report_id = self.sm.create_report(client_id, intake_id, intake["intake_type"])
        art = self.rg.generate_report(
            client_name=client["client_name_full"],
            report_id=report_id,
            client_id=client_id,
            findings=scored,
            report_type=intake["intake_type"]
        )
        
        self.sm.data["reports"][report_id]["artifacts"].update({
            "final_pdf_path": art["pdf_path"],
            "arco_files_paths": [art["arco_dir"]] if art.get("arco_dir") else []
        })
        self.sm.save_data()

        # 5. Quality Control Gate
        if self._run_qc_gate(report_id, art):
            self._dispatch_notification(report_id, client, intake, art["pdf_path"])
        else:
            self._handle_qc_failure(client_id, report_id)

    def _resolve_target(self, intake: Dict[str, Any], client: Dict[str, Any]) -> str:
        """Pick the best target (Email > Name > Slug) for scanning.
        
        Args:
            intake: The intake dictionary.
            client: The client dictionary.
            
        Returns:
            str: The target string to scan.
        """
        # 1. Email (Best for OSINT)
        emails = intake.get("identity", {}).get("emails", [])
        if emails:
            return emails[0]
            
        # 2. Full Name
        if client.get("client_name_full"):
            return client["client_name_full"]
            
        # 3. Fallback to Slug/ID
        return client.get("client_dir", "unknown_target")

    def _persist_raw_data(self, client_dir: str, intake_id: str, data: List[Dict[str, Any]]) -> str:
        """Save raw findings to the data directory.
        
        Args:
            client_dir: The directory name for the client.
            intake_id: The unique intake identifier.
            data: The list of raw findings to save.
            
        Returns:
            str: The full path to the saved file.
        """
        path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), '04_Data', 'raw', client_dir, intake_id)
        os.makedirs(path, exist_ok=True)
        full_path = os.path.join(path, 'spiderfoot.json')
        with open(full_path, 'w', encoding='utf-8') as f:
            json.dump(data, f)
        return full_path

    def _run_qc_gate(self, report_id: str, artifacts: Dict[str, Any]) -> bool:
        """Run the quality checklist and log results.
        
        Args:
            report_id: The ID of the report to check.
            artifacts: Dictionary containing artifact paths (e.g., pdf_path).
            
        Returns:
            bool: True if QC is approved, False otherwise.
        """
        res = self.qc.run_qc_checklist(report_id, artifacts["pdf_path"])
        # Fix: Use safer suffix replacement to avoid overwriting the report if extensions match or are missing
        base_path = os.path.splitext(artifacts["pdf_path"])[0]
        qc_file = f"{base_path}_QC.json"
        with open(qc_file, 'w', encoding='utf-8') as f:
            json.dump(res, f, indent=4)
        
        self.sm.update_qc_status(report_id, res["qc_status"])
        self.sm.data["reports"][report_id]["artifacts"]["qc_checklist_json_path"] = qc_file
        self.sm.save_data()
        return res["qc_status"] == "APROBADO"

    def _dispatch_notification(self, report_id: str, client: Dict[str, Any], intake: Dict[str, Any], pdf_path: str) -> None:
        """Send the report to the client and admin.
        
        Args:
            report_id: The report ID.
            client: The client dictionary.
            intake: The intake dictionary.
            pdf_path: Path to the generated PDF report.
        """
        recipients = list(intake.get("identity", {}).get("emails", []))
        if not recipients: recipients = ["unknown@example.com"]
        
        admin_copy = "info@felipemiramontesr.net"
        if admin_copy not in recipients: recipients.append(admin_copy)

        success, msg_id = self.notifier.send_report(recipients, pdf_path, client["client_name_full"], scan_id=report_id)
        if success:
            self.sm.update_report_status(report_id, "EN_REVISION")
            print(f"[+] Pipeline COMPLETED for {report_id}")
        else:
            print(f"[!] Notification failed for {report_id}")

    def _handle_qc_failure(self, client_id: str, report_id: str) -> None:
        """Process a failed QC gate by invalidating the report and creating a rescue intake.
        
        Args:
            client_id: The client identifier.
            report_id: The failed report identifier.
        """
        print(f"[!] QC Failure for {report_id}. Invalidating and creating RESCUE.")
        self.sm.update_report_status(report_id, "INVALIDADO", actor="SYSTEM", invalidated_reason="QC_FAIL")
        rescue_id = self.sm.create_intake(client_id, "RESCUE", requested_by="SYSTEM", replaces_report_id=report_id)
        print(f"[+] RESCUE created: {rescue_id}")

    def run_automatic_scheduler(self) -> None:
        """Scan all authorized intakes according to priority rules."""
        pending = self.sm.list_authorized_intakes_by_priority()
        print(f"[*] Scheduler: {len(pending)} pending tasks.")
        for task in pending:
            try:
                self.execute_pipeline(task["intake_id"])
            except Exception as e:
                print(f"[CRITICAL] Intake {task['intake_id']} failed: {e}")

if __name__ == "__main__":
    Orchestrator().run_automatic_scheduler()
