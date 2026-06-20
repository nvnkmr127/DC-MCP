import json
import os

log_file = "/Users/naveenadicharla/.gemini/antigravity-ide/brain/3f7aaf0a-4658-48b3-9cd1-85e2c474be52/.system_generated/logs/transcript.jsonl"
workspace = "/Users/naveenadicharla/Documents/DC MCP"

def safe_load(val):
    if isinstance(val, str):
        try:
            return json.loads(val)
        except:
            pass
    return val

ops = []
try:
    with open(log_file, 'r') as f:
        for line in f:
            data = json.loads(line)
            if 'tool_calls' in data:
                for tc in data['tool_calls']:
                    args = tc.get('args', {})
                    name = tc.get('name')
                    
                    if name == 'write_to_file':
                        tf = safe_load(args.get('TargetFile'))
                        cc = safe_load(args.get('CodeContent'))
                        if tf and isinstance(tf, str) and tf.startswith(workspace):
                            ops.append(('write', tf, cc))
                            
                    elif name == 'replace_file_content':
                        tf = safe_load(args.get('TargetFile'))
                        sl = safe_load(args.get('StartLine'))
                        el = safe_load(args.get('EndLine'))
                        targ = safe_load(args.get('TargetContent'))
                        repl = safe_load(args.get('ReplacementContent'))
                        if tf and isinstance(tf, str) and tf.startswith(workspace):
                            ops.append(('replace', tf, sl, el, targ, repl))
                            
                    elif name == 'multi_replace_file_content':
                        tf = safe_load(args.get('TargetFile'))
                        chunks = safe_load(args.get('ReplacementChunks'))
                        if isinstance(chunks, str):
                            try:
                                chunks = json.loads(chunks)
                            except:
                                chunks = None
                        if tf and isinstance(tf, str) and tf.startswith(workspace) and isinstance(chunks, list):
                            ops.append(('multi_replace', tf, chunks))
except Exception as e:
    print(f"Error reading transcript: {e}")

print(f"Found {len(ops)} operations to replay.")

files_content = {}

for op in ops:
    if op[0] == 'write':
        file, content = op[1], op[2]
        files_content[file] = content.splitlines(True) if content else []
    elif op[0] == 'replace':
        file, sl, el, targ, repl = op[1], op[2], op[3], op[4], op[5]
        if file not in files_content and os.path.exists(file):
            with open(file, 'r') as f:
                files_content[file] = f.readlines()
        
        if file in files_content:
            lines = files_content[file]
            try:
                sl, el = int(sl), int(el)
                if sl >= 1 and el <= len(lines):
                    new_lines = []
                    if repl:
                        new_lines = [r + '\n' for r in repl.split('\n')]
                        if not repl.endswith('\n'):
                            new_lines[-1] = new_lines[-1][:-1]
                    lines[sl-1:el] = new_lines
                files_content[file] = lines
            except:
                pass
    elif op[0] == 'multi_replace':
        file, chunks = op[1], op[2]
        if file not in files_content and os.path.exists(file):
            with open(file, 'r') as f:
                files_content[file] = f.readlines()
        
        if file in files_content:
            lines = files_content[file]
            valid_chunks = []
            for c in chunks:
                try:
                    c['StartLine'] = int(safe_load(c.get('StartLine')))
                    c['EndLine'] = int(safe_load(c.get('EndLine')))
                    valid_chunks.append(c)
                except:
                    pass
            valid_chunks.sort(key=lambda c: c['StartLine'], reverse=True)
            for chunk in valid_chunks:
                sl = chunk['StartLine']
                el = chunk['EndLine']
                repl = safe_load(chunk.get('ReplacementContent'))
                if sl >= 1 and el <= len(lines):
                    new_lines = []
                    if repl:
                        new_lines = [r + '\n' for r in repl.split('\n')]
                        if not repl.endswith('\n'):
                            new_lines[-1] = new_lines[-1][:-1]
                    lines[sl-1:el] = new_lines
            files_content[file] = lines

for file, lines in files_content.items():
    os.makedirs(os.path.dirname(file), exist_ok=True)
    with open(file, 'w') as f:
        f.writelines(lines)
    print(f"Restored {file}")

