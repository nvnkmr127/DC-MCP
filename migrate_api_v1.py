import os
import glob
import re

base_dir = '/Users/naveenadicharla/Documents/DC MCP'
modules_dir = os.path.join(base_dir, 'app/Modules')
js_dir = os.path.join(base_dir, 'resources/js')

print("Starting API V1 migration...")

# 1. Move controllers and update namespaces
for module in os.listdir(modules_dir):
    api_dir = os.path.join(modules_dir, module, 'Http/Controllers/Api')
    if not os.path.isdir(api_dir):
        continue
    
    v1_dir = os.path.join(api_dir, 'V1')
    os.makedirs(v1_dir, exist_ok=True)
    
    for file in os.listdir(api_dir):
        if file.endswith('.php'):
            old_path = os.path.join(api_dir, file)
            new_path = os.path.join(v1_dir, file)
            
            with open(old_path, 'r') as f:
                content = f.read()
                
            # Update namespace
            content = re.sub(
                r'namespace (App\\Modules\\[a-zA-Z0-9_]+\\Http\\Controllers\\Api);',
                r'namespace \1\\V1;',
                content
            )
            
            with open(new_path, 'w') as f:
                f.write(content)
                
            os.remove(old_path)
            print(f"Moved and updated namespace for {file} in {module}")

# 2. Update routes/api.php
for module in os.listdir(modules_dir):
    routes_file = os.path.join(modules_dir, module, 'routes/api.php')
    if not os.path.isfile(routes_file):
        continue
        
    with open(routes_file, 'r') as f:
        content = f.read()
        
    # Check if already has v1 prefix group to avoid double wrapping
    if "Route::prefix('v1')" not in content and "Route::prefix(\"v1\")" not in content:
        # Update use statements
        content = re.sub(
            r'use (App\\Modules\\[a-zA-Z0-9_]+\\Http\\Controllers\\Api)\\([a-zA-Z0-9_]+);',
            r'use \1\\V1\\\2;',
            content
        )
        
        # We need to wrap everything after the use statements in Route::prefix('v1')
        lines = content.split('\n')
        new_lines = []
        in_group = False
        for line in lines:
            if line.startswith('Route::') and not in_group:
                new_lines.append("Route::prefix('v1')->group(function () {")
                in_group = True
            
            if in_group:
                new_lines.append("    " + line)
            else:
                new_lines.append(line)
                
        if in_group:
            new_lines.append("});")
            
        with open(routes_file, 'w') as f:
            f.write('\n'.join(new_lines))
        print(f"Updated routes for {module}")

# 3. Update React frontend
js_files = glob.glob(os.path.join(js_dir, '**/*.ts*'), recursive=True) + \
           glob.glob(os.path.join(js_dir, '**/*.js*'), recursive=True)

for js_file in js_files:
    if not os.path.isfile(js_file):
        continue
        
    with open(js_file, 'r') as f:
        content = f.read()
        
    if '/api/' in content:
        # Replace /api/ with /api/v1/ ONLY if it's not already /api/v1/
        new_content = re.sub(r'/api/(?!v1/)', r'/api/v1/', content)
        
        if new_content != content:
            with open(js_file, 'w') as f:
                f.write(new_content)
            print(f"Updated API endpoints in {os.path.basename(js_file)}")

print("Done!")
