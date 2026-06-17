import os
import re
import shutil

MODULES_DIR = 'app/Modules'

modules = [d for d in os.listdir(MODULES_DIR) if os.path.isdir(os.path.join(MODULES_DIR, d))]

for mod in modules:
    controllers_dir = os.path.join(MODULES_DIR, mod, 'Http', 'Controllers')
    if not os.path.isdir(controllers_dir):
        continue

    web_dir = os.path.join(controllers_dir, 'Web')
    api_dir = os.path.join(controllers_dir, 'Api')
    os.makedirs(web_dir, exist_ok=True)
    os.makedirs(api_dir, exist_ok=True)

    controllers = [f for f in os.listdir(controllers_dir) if f.endswith('Controller.php') and os.path.isfile(os.path.join(controllers_dir, f))]
    
    for ctrl in controllers:
        src = os.path.join(controllers_dir, ctrl)
        
        if ctrl.endswith('WebController.php') or ctrl == 'PortalController.php':
            sub = 'Web'
        elif ctrl.endswith('ApiController.php'):
            sub = 'Api'
        else:
            sub = 'Web'
            
        target_dir = os.path.join(controllers_dir, sub)
        target = os.path.join(target_dir, ctrl)
        
        shutil.move(src, target)
        
        with open(target, 'r') as f:
            content = f.read()
            
        new_content = re.sub(
            r'(namespace\s+App\\Modules\\[a-zA-Z0-9_]+\\Http\\Controllers);',
            r'\1\\' + sub + ';',
            content
        )
        
        with open(target, 'w') as f:
            f.write(new_content)

    routes_dir = os.path.join(MODULES_DIR, mod, 'routes')
    if os.path.isdir(routes_dir):
        route_files = [f for f in os.listdir(routes_dir) if f.endswith('.php')]
        for r_file in route_files:
            r_path = os.path.join(routes_dir, r_file)
            with open(r_path, 'r') as f:
                content = f.read()
                
            def replace_use(match):
                namespace_base = match.group(1)
                controller_name = match.group(2)
                
                if controller_name.endswith('WebController') or controller_name == 'PortalController':
                    sub = 'Web'
                elif controller_name.endswith('ApiController'):
                    sub = 'Api'
                else:
                    sub = 'Web'
                    
                return f"use {namespace_base}\\{sub}\\{controller_name};"

            new_content = re.sub(
                r'use\s+(App\\Modules\\[a-zA-Z0-9_]+\\Http\\Controllers)\\([a-zA-Z0-9_]+Controller);',
                replace_use,
                content
            )
            
            with open(r_path, 'w') as f:
                f.write(new_content)
                
print("Refactoring complete.")
