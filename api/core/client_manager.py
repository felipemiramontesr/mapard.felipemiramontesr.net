import os
import json
import re
from datetime import datetime
from pathlib import Path

class ClientManager:
    """
    Manages client onboarding, ID generation, and directory structures.
    Follows the nomenclature: MAPA-RD-[ClientName]-[ID]
    """
    
    BASE_PATH = r"c:\Felipe\Projects\Mapa-rd\04_Data\reports"
    REGISTRY_PATH = r"c:\Felipe\Projects\Mapa-rd\04_Data\client_registry.json"

    def __init__(self):
        self._ensure_infrastructure()
        self.registry = self._load_registry()

    def _ensure_infrastructure(self):
        """Ensures base report directory exists."""
        if not os.path.exists(self.BASE_PATH):
            os.makedirs(self.BASE_PATH)

    def _load_registry(self):
        """Loads the registry JSON. Creates a default one if missing."""
        if not os.path.exists(self.REGISTRY_PATH):
            default_registry = {
                "next_global_client_id": 1,
                "clients": {}
            }
            with open(self.REGISTRY_PATH, 'w', encoding='utf-8') as f:
                json.dump(default_registry, f, indent=4)
            return default_registry
        else:
            with open(self.REGISTRY_PATH, 'r', encoding='utf-8') as f:
                return json.load(f)

    def _save_registry(self):
        """Saves current state to registry JSON."""
        with open(self.REGISTRY_PATH, 'w', encoding='utf-8') as f:
            json.dump(self.registry, f, indent=4)

    def _normalize_name(self, name):
        """
        Normalizes client name for folder usage.
        'Felipe de Jesús' -> 'Felipe_de_Jesus'
        """
        import unicodedata
        
        # Replace spaces with underscores
        name = name.replace(" ", "_")
        
        # Normalize unicode characters (NFD) and strip accents (combining chars)
        name = unicodedata.normalize('NFKD', name)
        name = "".join([c for c in name if not unicodedata.combining(c)])
        
        # Remove any remaining special chars (keep only alphanumeric and underscores)
        name = re.sub(r'[^a-zA-Z0-9_]', '', name)
        
        return name

    def create_client(self, full_name, email):
        """
        Onboards a new client.
        1. Generates ID (C00X)
        2. Creates Directory
        3. Creates Metadata File
        """
        normalized_name = self._normalize_name(full_name)
        
        # Check if client already exists (by email to avoid duplicates)
        for cid, data in self.registry["clients"].items():
            if data["meta"].get("email") == email:
                print(f"Client already exists: {cid} ({data['folder_name']})")
                return cid

        # Generate ID
        numeric_id = self.registry["next_global_client_id"]
        client_id = f"C{numeric_id:03d}"
        
        # Folder Name: MAPA-RD-Nombre-ID
        folder_name = f"MAPA-RD-{normalized_name}-{client_id}"
        client_dir = os.path.join(self.BASE_PATH, folder_name)

        # Create Directory
        if not os.path.exists(client_dir):
            os.makedirs(client_dir)

        # Create Onboarding Directory
        # Nomenclature: MAPA-RD-[ClientName]-[ID]-ONBOARDING
        onboarding_folder = f"{folder_name}-ONBOARDING"
        os.makedirs(os.path.join(client_dir, onboarding_folder), exist_ok=True)

        # Metadata
        metadata = {
            "id": client_id,
            "name": full_name,
            "normalized_name": normalized_name,
            "folder_name": folder_name,
            "next_report_id": 1,
            "meta": {
                "email": email,
                "created_at": datetime.now().isoformat()
            }
        }

        # Save Client Metadata File
        meta_path = os.path.join(client_dir, f"{folder_name}.json")
        with open(meta_path, 'w', encoding='utf-8') as f:
            json.dump(metadata, f, indent=4)

        # Update Registry
        self.registry["clients"][client_id] = metadata
        self.registry["next_global_client_id"] += 1
        self._save_registry()

        print(f"Client Created: {client_id} -> {client_dir}")
        return client_id

    def create_report(self, client_id):
        """
        Creates a new report structure for a client.
        1. Generates Report ID (R00X)
        2. Creates Subdirectories (raw, PDF)
        """
        if client_id not in self.registry["clients"]:
            raise ValueError(f"Client ID {client_id} not found.")

        client_data = self.registry["clients"][client_id]
        
        # Generate Report ID
        numeric_rid = client_data["next_report_id"]
        report_id = f"R{numeric_rid:03d}"
        
        # Date (ISO)
        today_iso = datetime.now().strftime('%Y-%m-%d')
        
        # Folder Name: MAPA-RD-Nombre-CID-RID-Date
        report_folder_name = f"{client_data['folder_name']}-{report_id}-{today_iso}"
        
        # Full Path
        client_dir = os.path.join(self.BASE_PATH, client_data['folder_name'])
        report_dir = os.path.join(client_dir, report_folder_name)
        
        # Create Structure
        os.makedirs(os.path.join(report_dir, "raw"), exist_ok=True)
        os.makedirs(os.path.join(report_dir, "PDF"), exist_ok=True)
        
        # Update Registry
        self.registry["clients"][client_id]["next_report_id"] += 1
        self._save_registry()
        
        print(f"Report Infrastructure Created: {report_id} -> {report_dir}")
        return report_dir

if __name__ == "__main__":
    # Test Execution
    manager = ClientManager()
    
    # 1. Create Pilot Client
    print("--- Creating Client ---")
    cid = manager.create_client(
        full_name="Felipe de Jesus Miramontes Romero", 
        email="felipemiramontesr@gmail.com"
    )
    
    # 2. Create First Report (Disabled for Onboarding Test)
    # print("\n--- Creating Report ---")
    # report_path = manager.create_report(cid)
    
    # print("\nSUCCESS. Infrastructure ready.")
    print(f"\nSUCCESS. Client Onboarded: {cid}")
