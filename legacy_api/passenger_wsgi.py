import sys
import os

# Minimal WSGI Application for Isolation Testing
# No imports from 'api', no external libs. just pure python.


def application(environ, start_response):
    status = "200 OK"
    response_headers = [("Content-type", "text/plain")]
    start_response(status, response_headers)
    return [b"Hello World from Passenger! The server is running correctly."]
