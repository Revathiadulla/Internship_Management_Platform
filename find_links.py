import os
import re

with open('links.txt', 'w', encoding='utf-8') as out:
    for filename in os.listdir('.'):
        if filename.endswith('.html'):
            with open(filename, 'r', encoding='utf-8') as f:
                content = f.read()
                # find all <a> tags
                a_tags = re.findall(r'<a\s+[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)</a>', content, re.IGNORECASE | re.DOTALL)
                for href, text in a_tags:
                    if href in ['#', '']:
                        clean_text = re.sub(r'<[^>]+>', '', text).strip()
                        if clean_text:
                            out.write(f"{filename}: {clean_text}\n")
