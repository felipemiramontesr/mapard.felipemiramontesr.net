from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import os

# Initialize FastAPI with metadata
app = FastAPI(
    title="MAPA-RD Intel Engine",
    description="NSA-Level OSINT Orchestration API",
    version="2.0.0",
)

# CORS Configuration (Security)
# Allow the frontend domain and localhost for dev
origins = [
    "http://localhost:5173",
    "http://localhost:4173",
    "https://mapa-rd.felipemiramontesr.net",
    "http://mapa-rd.felipemiramontesr.net",
]

app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

from api.routes import scans

app.include_router(scans.router, prefix="/api")


@app.get("/")
async def root():
    """Health Check Endpoint"""
    return {
        "system": "MAPA-RD Intel Engine",
        "status": "ONLINE",
        "version": "2.0.0-alpha",
        "mode": "Hostinger/Passenger",
    }


@app.get("/api/status")
async def status():
    """Detailed System Status"""
    return JSONResponse(content={"engine": "Ready", "queue": 0, "active_nodes": 1})
