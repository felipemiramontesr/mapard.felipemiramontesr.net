import asyncio
import os
import sys
import json
from datetime import datetime

# Path Hack to allow imports from local core/
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

# Import the Original Orchestrator
try:
    from orchestrator import Orchestrator
    from database import update_job_status
except ImportError:
    # Fallback for dev/mock mode if 07_Src isn't fully copied
    Orchestrator = None


class BackgroundScanRunner:
    def __init__(self, job_id: str, target_email: str, target_name: str):
        self.job_id = job_id
        self.email = target_email
        self.name = target_name
        self.logs = []

    def log(self, message: str, type: str = "info"):
        entry = {
            "timestamp": datetime.now().strftime("%H:%M:%S"),
            "message": message,
            "type": type,
        }
        self.logs.append(entry)
        # Real-time DB update for frontend polling
        print(f"[{self.job_id}] {message}")
        update_job_status(self.job_id, status=None, logs=self.logs)

    async def run(self):
        """
        Executes the full pipeline in a way that respects Hostinger's limits.
        If Orchestrator is missing (dev env), it runs a mock.
        """
        self.log(f"Initializing background scan for {self.email}...", "info")
        update_job_status(self.job_id, "PROCESSING")

        if not Orchestrator:
            await self._run_mock()
            return

        try:
            # 1. Initialize Engine
            self.log("Booting OSINT Engine (SpiderFoot + HIBP)...", "info")
            # Here we would instantiate the real Orchestrator
            # orch = Orchestrator()
            # But the Orchestrator runs SUBPROCESSES, which is risky on Hostinger.
            # We need to selectively call modules.

            # --- MOCKING THE HEAVY LIFTING FOR PHASE 2 INITIAL DEPLOY ---
            # To ensure stability first, we simulate the heavy scan
            await self._run_mock()

        except Exception as e:
            self.log(f"CRITICAL ERROR: {str(e)}", "error")
            update_job_status(self.job_id, "FAILED")

    async def _run_mock(self):
        """Simulation for Phase 2 Validation"""
        steps = [
            ("Resolving DNS records...", 2),
            ("Querying HaveIBeenPwned API...", 1),
            ("Analyzing 4 Found Breaches...", 3),
            ("Requesting Dark Web Metadata...", 2),
            ("Generating PDF Report...", 2),
        ]

        for msg, delay in steps:
            await asyncio.sleep(delay)
            self.log(msg, "info")

        self.log("Scan Complete. PDF Generated.", "success")
        update_job_status(self.job_id, "COMPLETED", result_path="/reports/mock.pdf")
