import os
import re

directory = 'c:/xampp/htdocs/IMP'

def add_alert_class(content, msg_var, alert_class):
    # Find all instances of $success_msg or $error_msg echos
    # They are usually inside a <div> or <span> that is a child of an alert container.
    # We want to find the nearest parent element that has class="..." and add our alert class.
    
    # A safer approach is to find <?php if ($success_msg): ?> and the next opening tag with class="..."
    # Example:
    # <?php if ($success_msg): ?>
    #     <div class="p-4 text-sm text-green-800...">
    
    # Regex to match <?php if ($success_msg...): ?> followed by <... class="...">
    pattern = r'(<\?php\s+if\s*\(\s*[^)]*?\$' + msg_var + r'[^)]*\)\s*:\s*\?>\s*<[a-zA-Z0-9_-]+[^>]*class=")([^"]*)(")'
    
    def replacer(match):
        prefix = match.group(1)
        classes = match.group(2)
        suffix = match.group(3)
        if alert_class not in classes:
            classes = classes + ' ' + alert_class
        return prefix + classes + suffix
        
    content = re.sub(pattern, replacer, content, flags=re.IGNORECASE)
    
    # What if it's isset($_GET['success'])?
    pattern2 = r'(<\?php\s+if\s*\(\s*isset\(\$_GET\[\'success\'\]\)\s*\)\s*:\s*\?>\s*<[a-zA-Z0-9_-]+[^>]*class=")([^"]*)(")'
    if msg_var == 'success_msg':
        content = re.sub(pattern2, replacer, content, flags=re.IGNORECASE)
        
    pattern3 = r'(<\?php\s+if\s*\(\s*isset\(\$_GET\[\'error\'\]\)\s*\)\s*:\s*\?>\s*<[a-zA-Z0-9_-]+[^>]*class=")([^"]*)(")'
    if msg_var == 'error_msg':
        content = re.sub(pattern3, replacer, content, flags=re.IGNORECASE)

    return content

for root, dirs, files in os.walk(directory):
    for file in files:
        if file.endswith('.php') or file.endswith('.html'):
            filepath = os.path.join(root, file)
            
            # Skip this script or external libraries
            if 'PHPMailer' in filepath or 'update_alerts.py' in filepath:
                continue
                
            with open(filepath, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
                
            original_content = content
            
            # Add alert classes
            content = add_alert_class(content, 'success_msg', 'alert-success')
            content = add_alert_class(content, 'error_msg', 'alert-danger')
            content = add_alert_class(content, 'warning_msg', 'alert-warning')
            content = add_alert_class(content, 'info_msg', 'alert-info')
            
            # Inject JS if not already there
            if ('alert-success' in content or 'alert-danger' in content or 'alert-warning' in content or 'alert-info' in content) and 'alerts.js' not in content:
                # Add before </body>
                if '</body>' in content:
                    content = content.replace('</body>', '<script src="js/alerts.js"></script>\n</body>')
                else:
                    # If no body tag, append to end
                    content += '\n<script src="js/alerts.js"></script>\n'
            
            if content != original_content:
                with open(filepath, 'w', encoding='utf-8') as f:
                    f.write(content)
                print(f"Updated: {filepath}")

print("Done updating files.")
