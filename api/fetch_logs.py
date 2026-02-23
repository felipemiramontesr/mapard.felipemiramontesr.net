import requests
import urllib3
urllib3.disable_warnings()

url = "https://mapard.felipemiramontesr.net/api/investigate.php?auth=zero_day_wipe"
headers = {"User-Agent": "Mozilla/5.0"}

try:
    response = requests.get(url, headers=headers, verify=False)
    print("STATUS CODE:", response.status_code)
    print("OUTPUT:")
    print(response.text)
except Exception as e:
    print("ERROR:", str(e))
