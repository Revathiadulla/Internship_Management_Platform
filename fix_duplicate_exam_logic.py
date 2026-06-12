import re

with open('hr/applicant_detail.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Split the file by `// Exam Modal Logic`
marker = "    // Exam Modal Logic"
blocks = content.split(marker)

if len(blocks) > 2:
    print(f"Found {len(blocks) - 1} instances of the marker. Removing the extra ones.")
    
    # We want blocks[0] + marker + blocks[1]
    # But wait, what if blocks[1] doesn't have `</script>`?
    # blocks[1] ends right before the second marker.
    # The actual `</script>` is at the very end of the LAST block.
    
    new_content = blocks[0] + marker + blocks[-1]
    
    with open('hr/applicant_detail.php', 'w', encoding='utf-8') as fw:
        fw.write(new_content)
    print("Fixed!")
else:
    print("No duplicates found.")
