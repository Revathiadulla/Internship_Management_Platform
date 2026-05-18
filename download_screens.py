import json
import urllib.request
import os

with open(r'C:\Users\Revathi.DESKTOP-I0DEJU6\.gemini\antigravity\brain\0e08ca28-a0c0-41d1-9152-c59046fc8552\.system_generated\steps\27\output.txt', 'r', encoding='utf-8') as f:
    content = f.read()

# the file might have some extra prefixes, let's extract the json
json_str = content[content.find('{'):]
data = json.loads(json_str)

screen_mapping = {
    '48af5531f949413db7ab09138f6d37a9': 'login.html',
    '9639ffb1f0654b9eb9a710f54fc38188': 'index.html',
    '24d25e354b67487e94c0c472ebb89250': 'register.html',
    'e123e6c948d749b694e05158712e8154': 'dashboard.html',
    '232b2b2db5e24f6a91b21c4ce017c97a': 'application.html',
    '72df204d6a094af8b16fa2ed5be90b34': 'company.html'
}

for screen in data.get('screens', []):
    screen_id = screen.get('name', '').split('/')[-1]
    if screen_id in screen_mapping:
        url = screen.get('htmlCode', {}).get('downloadUrl')
        filename = screen_mapping[screen_id]
        if url:
            print(f'Downloading {filename}...')
            try:
                urllib.request.urlretrieve(url, filename)
                print(f'Saved {filename}')
            except Exception as e:
                print(f'Error downloading {filename}: {e}')
