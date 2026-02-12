import sys
import os
import traceback

# 1. Add current directories
sys.path.append(os.getcwd())
sys.path.append(os.path.join(os.getcwd(), "api"))

try:
    # 2. Try to import the FastAPI app
    from api.main import app as application
except Exception as e:
    # 3. Fallback for Debugging (Instead of 503)
    error_msg = f"Startup Error:\n{traceback.format_exc()}"
    
    def application(environ, start_response):
        status = '200 OK'
        response_headers = [('Content-type', 'text/plain')]
        start_response(status, response_headers)
        return [error_msg.encode()]
