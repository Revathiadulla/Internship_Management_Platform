import json
import urllib.request
import os
import re

def sanitize_filename(name):
    # Remove invalid characters and spaces
    name = re.sub(r'[<>:"/\\|?*]', '', name)
    name = name.replace(' ', '_').lower()
    return name + '.html'

with open(r'C:\Users\Revathi.DESKTOP-I0DEJU6\.gemini\antigravity\brain\0e08ca28-a0c0-41d1-9152-c59046fc8552\.system_generated\steps\27\output.txt', 'r', encoding='utf-8') as f:
    content = f.read()

# Extract the JSON part
json_str = content[content.find('{'):]
data = json.loads(json_str)

for screen in data.get('screens', []):
    title = screen.get('title', 'Unknown Screen')
    filename = sanitize_filename(title)
    url = screen.get('htmlCode', {}).get('downloadUrl')
    
    if url:
        print(f'Downloading {filename}...')
        try:
            urllib.request.urlretrieve(url, filename)
            print(f'Saved {filename}')
        except Exception as e:
            print(f'Error downloading {filename}: {e}')
