import sys
import os

# 1. Add the current directory to sys.path so we can import 'api'
sys.path.append(os.getcwd())
sys.path.append(os.path.join(os.getcwd(), "api"))

# 2. Point to the FastAPI app
# In Hostinger/Passenger, the callable usually needs to be named 'application'
from api.main import app as application

# 3. Optional: Middleware for debugging if needed
# from werkzeug.debug import DebuggedApplication
# application = DebuggedApplication(application, evalex=True)
