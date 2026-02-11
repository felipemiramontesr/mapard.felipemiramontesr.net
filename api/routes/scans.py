from fastapi import APIRouter, BackgroundTasks, HTTPException
from pydantic import BaseModel, EmailStr
import uuid
import sys
import os

# Import Database & Core Wrapper
sys.path.append(os.path.join(os.getcwd(), 'api'))
from database import create_job, get_job
from core.wrapper import BackgroundScanRunner

router = APIRouter()

class ScanRequest(BaseModel):
    name: str
    email: EmailStr
    domain: str = None  # Optional

class ScanResponse(BaseModel):
    job_id: str
    message: str
    status: str

@router.post("/scan", response_model=ScanResponse, status_code=202)
async def start_scan(request: ScanRequest, background_tasks: BackgroundTasks):
    """
    Initiates an OSINT scan.
    Returns immediately with a Job ID. The scan runs in the background.
    """
    try:
        job_id = str(uuid.uuid4())
        
        # 1. Create Job in SQLite (Persistence)
        create_job(job_id, request.name, request.email)
        
        # 2. Instantiate Runner
        runner = BackgroundScanRunner(job_id, request.email, request.name)
        
        # 3. Queue Background Task (Async Execution)
        background_tasks.add_task(runner.run)
        
        return {
            "job_id": job_id,
            "message": "Protocol initialized. Intelligence gathering started.",
            "status": "PENDING"
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/scan/{job_id}")
async def check_scan_status(job_id: str):
    """
    Polling endpoint for the Frontend.
    Returns current status and live logs.
    """
    job = get_job(job_id)
    if not job:
        raise HTTPException(status_code=404, detail="Job not found")
        
    return {
        "job_id": job['id'],
        "status": job['status'],
        "target": job['target_email'],
        "logs": job['logs'], # JSON string, frontend needs to parse
        "result_url": job['result_path']
    }
