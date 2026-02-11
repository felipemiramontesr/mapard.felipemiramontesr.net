import os
import json
import re
from datetime import datetime, timedelta
from typing import List, Dict, Any, Optional, Union, Tuple

# Constants for project-wide paths
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TRACKING_DIR = os.path.join(BASE_DIR, '04_Data', 'tracking')
PERSISTENCE_FILE = os.path.join(TRACKING_DIR, 'persistence.json')

class StateManager:
    """Core state persistence and lifecycle manager for MAPA-RD.
    
    This class manages the integrity of clients, intakes, reports, and logs.
    It enforces business rules, state transitions, and automatic migrations.
    """

    # Strict Enum Definitions (Reference for business logic)
    REPORT_TYPES = ["BASELINE", "FREQUENCY", "INCIDENT", "RESCUE", "MONTHLY", "ON_DEMAND"]
    REPORT_STATUSES = ["GENERADO", "EN_REVISION", "APROBADO_TACITO", "OBJETADO", "INVALIDADO"]
    QC_STATUSES = ["PENDIENTE", "APROBADO", "FALLIDO"]
    INTAKE_STATUSES = ["GENERADO", "AUTORIZADO", "EJECUTADO"]
    REQUESTED_BY = ["SYSTEM", "AG", "CLI_USER"]
    INVALIDATED_REASON = ["QC_FAIL", "CLIENT_ERROR_REAL"]
    CLIENT_TYPES = ["PF", "PM"]

    def __init__(self):
        """Initialize StateManager and load persistent data."""
        self.data: Dict[str, Any] = {}
        if not os.path.exists(TRACKING_DIR):
            os.makedirs(TRACKING_DIR, exist_ok=True)
        self.reload()

    def reload(self) -> None:
        """Reload data from disk and perform necessary migrations."""
        self._load_data()
        self._migrate_legacy_keys()

    def _load_data(self) -> None:
        """Load JSON data from PERSISTENCE_FILE or initialize defaults."""
        if os.path.exists(PERSISTENCE_FILE):
            try:
                with open(PERSISTENCE_FILE, 'r', encoding='utf-8') as f:
                    self.data = json.load(f)
            except (json.JSONDecodeError, IOError) as e:
                print(f"[!] Error loading persistence: {e}")
                self._init_empty_state()
        else:
            self._init_empty_state()
        
        # Ensure schema integrity
        for key in ["clients", "intakes", "reports", "logs"]:
            self.data.setdefault(key, {} if key != "logs" else [])

    def _init_empty_state(self) -> None:
        """Initialize an empty state structure."""
        self.data = {
            "clients": {},
            "intakes": {},
            "reports": {},
            "logs": []
        }
        self.save_data()

    def _migrate_legacy_keys(self) -> None:
        """Handle schema evolutions automatically."""
        changed = False
        for client_id, client in self.data.get("clients", {}).items():
            # Ensure all required keys exist (Migration)
            updated_client = self.ensure_client_defaults(client)
            if updated_client != client:
                self.data["clients"][client_id] = updated_client
                changed = True
        
        if changed:
            print("[INFO] Schema migration performed: Clients updated.")
            self.save_data()

    def save_data(self) -> None:
        """Persist current state to disk securely."""
        try:
            with open(PERSISTENCE_FILE, 'w', encoding='utf-8') as f:
                json.dump(self.data, f, indent=4, ensure_ascii=False)
        except IOError as e:
            print(f"[CRITICAL] Failed to save persistence data: {e}")

    # --- Client Management ---

    def ensure_client_defaults(self, state: Optional[Dict[str, Any]]) -> Dict[str, Any]:
        """Apply default values to a client state dictionary.
        
        Args:
            state: Existing client dictionary or None.
            
        Returns:
            A dictionary with all required client keys.
        """
        defaults = {
            "id": "",
            "client_name_full": "",
            "client_name_slug": "",
            "client_type": "PF",
            "incident_limit_month": 2,
            "incident_count_month": 0,
            "incident_month_key": datetime.now().strftime("%Y-%m"),
            "last_valid_report_id": None,
            "created_at": datetime.now().isoformat(),
            "reports": [],
            "intakes": [],
            "report_seq": 0,
            "intake_seq": 0,
            "client_dir": ""
        }
        base = state if state is not None else {}
        for k, v in defaults.items():
            base.setdefault(k, v)
        return base

    def create_client(self, client_id: str, name_full: str, client_type: str = "PF") -> str:
        """Register a new client in the system.
        
        Args:
            client_id: Unique string identifier (e.g. '0000001').
            name_full: Customer's real full name.
            client_type: 'PF' (Person) or 'PM' (Company).
            
        Returns:
            The registered client_id.
        """
        if client_id not in self.data["clients"]:
            slug = self.sanitize_slug(name_full)
            self.data["clients"][client_id] = self.ensure_client_defaults({
                "id": client_id,
                "client_name_full": name_full,
                "client_name_slug": slug,
                "client_type": client_type,
                "client_dir": slug
            })
            self.add_event_log("CLIENT", client_id, "CREATE", None, "CREATED")
            self.save_data()
        return client_id

    def get_client(self, client_id: str) -> Optional[Dict[str, Any]]:
        """Retrieve client data by ID with default enforcement."""
        client = self.data["clients"].get(client_id)
        if client:
             # Ensure defaults on the fly in case persistence was stale
             return self.ensure_client_defaults(client)
        return None

    def get_client_by_slug(self, slug: str) -> Optional[Dict[str, Any]]:
        """Find a client by their normalized name slug."""
        for client in self.data["clients"].values():
            if client.get("client_name_slug") == slug:
                return self.ensure_client_defaults(client)
        return None

    def update_client(self, client_id: str, **kwargs: Any) -> None:
        """Update client attributes with automatic default enforcement."""
        if client_id not in self.data["clients"]:
            self.create_client(client_id, kwargs.get("client_name_full", client_id))
        
        self.data["clients"][client_id].update(kwargs)
        self.save_data()

    def _get_or_create_client_id(self, client_name: str) -> str:
        """Helper to retrieve ID by name or generate new one."""
        # 1. Search existing
        for cid, data in self.data["clients"].items():
            if data.get("client_name_full") == client_name:
                return cid
        
        # 2. Generate new deterministic ID
        import hashlib
        # Use first 7 chars of hash for a short, readable ID
        new_id = hashlib.sha256(client_name.encode('utf-8')).hexdigest()[:7].upper()
        
        # Collision handling (unlikely but safe)
        while new_id in self.data["clients"]:
             new_id = hashlib.sha256((client_name + new_id).encode('utf-8')).hexdigest()[:7].upper()
             
        return new_id

    # --- Intake Management ---

    def create_intake(
        self, 
        client_id: str, 
        intake_type: str, 
        requested_by: str = "SYSTEM", 
        replaces_report_id: Optional[str] = None
    ) -> str:
        """Create a new service request (Intake).
        
        Args:
            client_id: Target client.
            intake_type: Must be one of REPORT_TYPES.
            requested_by: Source of request.
            replaces_report_id: Mandatory for RESCUE type.
            
        Returns:
            The unique intake_id (format: I-CLIENTID-SEQ).
        """
        if intake_type not in self.REPORT_TYPES:
            raise ValueError(f"Invalid Intake Type: {intake_type}")
        
        client = self.get_client(client_id)
        if not client:
            raise ValueError(f"Client {client_id} not found.")

        # Business Logic I7: Reset monthly counters if month changed
        self._reset_incident_counters(client_id)

        seq = client.get("intake_seq", 0) + 1
        intake_id = f"I-{client_id}-{seq:04d}"
        
        intake_data = {
            "intake_id": intake_id,
            "client_id": client_id,
            "intake_type": intake_type,
            "intake_status": "GENERADO",
            "created_at": datetime.now().isoformat(),
            "requested_by": requested_by,
            "replaces_report_id": replaces_report_id
        }
        
        self.data["intakes"][intake_id] = intake_data
        client["intakes"].append(intake_id)
        client["intake_seq"] = seq
        
        self.add_event_log("INTAKE", intake_id, "CREATE", None, "GENERADO", actor=requested_by)
        self.save_data()
        return intake_id

    def update_intake(self, intake_id: str, to_status: str, actor: str = "SYSTEM") -> None:
        """Handle intake state transitions with validation."""
        intake = self.data["intakes"].get(intake_id)
        if not intake:
            raise ValueError(f"Intake {intake_id} not found.")
        
        from_status = intake["intake_status"]
        self._validate_transition(from_status, to_status, self.INTAKE_STATUSES, {
            "GENERADO": ["AUTORIZADO"],
            "AUTORIZADO": ["EJECUTADO"]
        })

        intake["intake_status"] = to_status
        time_key = "authorized_at" if to_status == "AUTORIZADO" else "executed_at"
        intake[time_key] = datetime.now().isoformat()

        self.add_event_log("INTAKE", intake_id, "STATUS_CHANGE", from_status, to_status, actor=actor)
        self.save_data()

    # --- Report Management ---

    def create_report(self, client_id: str, intake_id: str, report_type: str) -> str:
        """Initialize a new report tracking record."""
        client = self.get_client(client_id)
        if not client:
            raise ValueError(f"Client {client_id} not found.")
        
        seq = client.get("report_seq", 0) + 1
        report_id = f"R-{client_id}-{seq:04d}"
        
        report_data = {
            "report_id": report_id,
            "client_id": client_id,
            "intake_id": intake_id,
            "report_type": report_type,
            "report_status": "GENERADO",
            "qc_status": "PENDIENTE",
            "created_at": datetime.now().isoformat(),
            "artifacts": {"final_pdf_path": None, "arco_files_paths": [], "qc_checklist_json_path": None}
        }
        
        self.data["reports"][report_id] = report_data
        client["reports"].append(report_id)
        client["report_seq"] = seq
        
        self.add_event_log("REPORT", report_id, "CREATE", None, "GENERADO")
        self.save_data()
        return report_id

    def update_report_status(
        self, 
        report_id: str, 
        to_status: str, 
        actor: str = "SYSTEM", 
        invalidated_reason: Optional[str] = None
    ) -> None:
        """Process report status changes and deadlines."""
        report = self.data["reports"].get(report_id)
        if not report:
            raise ValueError(f"Report {report_id} not found.")
        
        from_status = report["report_status"]
        
        # QC-Fail direct transition bypasses normal workflow
        if not (from_status == "GENERADO" and to_status == "INVALIDADO"):
             self._validate_transition(from_status, to_status, self.REPORT_STATUSES, {
                "GENERADO": ["EN_REVISION"],
                "EN_REVISION": ["APROBADO_TACITO", "OBJETADO"],
                "OBJETADO": ["INVALIDADO"]
            })

        report["report_status"] = to_status
        if to_status == "EN_REVISION":
             report["sent_at"] = datetime.now().isoformat()
             report["review_deadline_at"] = (datetime.now() + timedelta(hours=48)).isoformat()
        
        if invalidated_reason:
            report["invalidated_reason"] = invalidated_reason

        self.add_event_log("REPORT", report_id, "STATUS_CHANGE", from_status, to_status, actor=actor)
        self.save_data()

    def update_qc_status(self, report_id: str, to_status: str) -> None:
        """Update the Quality Control status."""
        report = self.data["reports"].get(report_id)
        if not report or report["qc_status"] != "PENDIENTE":
            raise ValueError(f"Report {report_id} invalid for QC update.")
        
        report["qc_status"] = to_status
        self.add_event_log("REPORT", report_id, "QC_CHANGE", "PENDIENTE", to_status)
        self.save_data()

    # --- Utilities & Internal Logic ---

    def add_event_log(self, etype: str, eid: str, action: str, fstate: Any, tstate: Any, actor: str = "SYSTEM") -> None:
        """Append a record to the global audit log."""
        self.data["logs"].append({
            "timestamp": datetime.now().isoformat(),
            "entity_type": etype,
            "entity_id": eid,
            "action": action,
            "from_state": fstate,
            "to_state": tstate,
            "actor": actor
        })

    def sanitize_slug(self, name: str) -> str:
        """Convert a string into a URL-safe, filesystem-safe slug."""
        if not name:
            return "unknown"
        s = name.lower().strip()
        s = re.sub(r'[áàäâ]', 'a', s)
        s = re.sub(r'[éèëê]', 'e', s)
        s = re.sub(r'[íìïî]', 'i', s)
        s = re.sub(r'[óòöô]', 'o', s)
        s = re.sub(r'[úùüû]', 'u', s)
        s = re.sub(r'[ñ]', 'n', s)
        s = re.sub(r'[^a-z0-9_-]', '-', s) # Replace everything else with dash
        s = re.sub(r'-+', '-', s)          # Collapse multiple dashes
        s = s.strip('-')
        return s or "client"

    def _validate_transition(self, f: str, t: str, allowed_statuses: List[str], rules: Dict[str, List[str]]) -> None:
        """Generic state machine validator."""
        if t not in allowed_statuses:
            raise ValueError(f"Invalid status: {t}")
        if t not in rules.get(f, []):
            raise ValueError(f"Illegal transition: {f} -> {t}")

    def _reset_incident_counters(self, client_id: str) -> None:
        """Reset monthly incident counts if a new month has started."""
        client = self.get_client(client_id)
        if not client: return
        curr = datetime.now().strftime("%Y-%m")
        if client.get("incident_month_key") != curr:
            client.update({"incident_count_month": 0, "incident_month_key": curr})

    def list_authorized_intakes_by_priority(self) -> List[Dict[str, Any]]:
        """Sort authorized intakes by global priority (RESCUE > INCIDENT > FREQUENCY > BASELINE)."""
        pmap = {"RESCUE": 0, "INCIDENT": 1, "FREQUENCY": 2, "BASELINE": 3}
        authorized = [i for i in self.data["intakes"].values() if i["intake_status"] == "AUTORIZADO"]
        authorized.sort(key=lambda x: (pmap.get(x["intake_type"], 99), x["created_at"]))
        return authorized
