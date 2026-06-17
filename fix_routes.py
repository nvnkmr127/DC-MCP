import os
import re

MODULES_DIR = 'app/Modules'

modules = [d for d in os.listdir(MODULES_DIR) if os.path.isdir(os.path.join(MODULES_DIR, d))]

for mod in modules:
    routes_dir = os.path.join(MODULES_DIR, mod, 'routes')
    if not os.path.isdir(routes_dir):
        continue

    route_files = [f for f in os.listdir(routes_dir) if f.endswith('.php')]
    for r_file in route_files:
        r_path = os.path.join(routes_dir, r_file)
        with open(r_path, 'r') as f:
            content = f.read()

        # Find string based routes: 'ControllerName@methodName'
        # e.g., Route::post('auth/login', 'LoginApiController@login');
        
        matches = re.findall(r"'([A-Za-z0-9_]+Controller)@([A-Za-z0-9_]+)'", content)
        
        if not matches:
            continue

        controllers_needed = set()

        for controller_name, method_name in matches:
            content = content.replace(f"'{controller_name}@{method_name}'", f"[{controller_name}::class, '{method_name}']")
            controllers_needed.add(controller_name)

        # Add use statements if missing
        lines = content.split('\n')
        
        for controller_name in controllers_needed:
            if controller_name.endswith('WebController') or controller_name == 'PortalController':
                sub = 'Web'
            elif controller_name.endswith('ApiController'):
                sub = 'Api'
            else:
                sub = 'Web'
                
            use_stmt = f"use App\\Modules\\{mod}\\Http\\Controllers\\{sub}\\{controller_name};"
            if use_stmt not in content:
                # Find the last use statement or after <?php
                insert_idx = 1
                for i, line in enumerate(lines):
                    if line.startswith('use '):
                        insert_idx = i + 1
                lines.insert(insert_idx, use_stmt)

        with open(r_path, 'w') as f:
            f.write('\n'.join(lines))

print("Routes updated to tuple syntax.")
