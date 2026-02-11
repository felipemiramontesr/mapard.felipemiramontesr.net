import sqlite3
import json
from datetime import datetime
import os

DB_PATH = os.path.join(os.path.dirname(__file__), '..', 'jobs.db')

def get_db_connection():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    conn = get_db_connection()
    c = conn.cursor()
    
    # Jobs Table: Tracks the status of every scan request
    c.execute('''
        CREATE TABLE IF NOT EXISTS jobs (
            id TEXT PRIMARY KEY,
            status TEXT NOT NULL, -- PENDING, PROCESSING, COMPLETED, FAILED
            target_name TEXT,
            target_email TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP,
            result_path TEXT,
            logs TEXT
        )
    ''')
    conn.commit()
    conn.close()

def create_job(job_id: str, name: str, email: str):
    conn = get_db_connection()
    c = conn.cursor()
    c.execute(
        "INSERT INTO jobs (id, status, target_name, target_email, logs) VALUES (?, ?, ?, ?, ?)",
        (job_id, 'PENDING', name, email, '[]')
    )
    conn.commit()
    conn.close()

def update_job_status(job_id: str, status: str, logs: list = None, result_path: str = None):
    conn = get_db_connection()
    c = conn.cursor()
    
    updates = []
    params = []
    
    if status:
        updates.append("status = ?")
        params.append(status)
    
    if result_path:
        updates.append("result_path = ?")
        params.append(result_path)
        updates.append("completed_at = ?")
        params.append(datetime.utcnow())
        
    if logs:
        updates.append("logs = ?")
        params.append(json.dumps(logs))
        
    updates.append("id = ?") # WHERE clause (placeholder, logic below is slight fix)
    
    # Correct SQL construction
    sql = f"UPDATE jobs SET {', '.join(updates[:-1])} WHERE id = ?"
    params.append(job_id)
    
    c.execute(sql, tuple(params))
    conn.commit()
    conn.close()

def get_job(job_id: str):
    conn = get_db_connection()
    job = conn.execute('SELECT * FROM jobs WHERE id = ?', (job_id,)).fetchone()
    conn.close()
    return dict(job) if job else None

# Initialize on module load (safe for sqlite)
init_db()
