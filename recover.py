import json

log_file = "/Users/naveenadicharla/.gemini/antigravity-ide/brain/3f7aaf0a-4658-48b3-9cd1-85e2c474be52/.system_generated/logs/transcript.jsonl"

writes = []
try:
    with open(log_file, 'r') as f:
        for line in f:
            data = json.loads(line)
            if 'tool_calls' in data:
                for tc in data['tool_calls']:
                    args = tc.get('args', {})
                    if tc['name'] == 'default_api:write_to_file':
                        writes.append({'type': 'write', 'file': args.get('TargetFile'), 'content': args.get('CodeContent')})
                    elif tc['name'] == 'default_api:replace_file_content':
                        writes.append({
                            'type': 'replace', 
                            'file': args.get('TargetFile'), 
                            'start': args.get('StartLine'), 
                            'end': args.get('EndLine'), 
                            'target': args.get('TargetContent'), 
                            'replacement': args.get('ReplacementContent')
                        })
                    elif tc['name'] == 'default_api:multi_replace_file_content':
                        writes.append({
                            'type': 'multi_replace',
                            'file': args.get('TargetFile'),
                            'chunks': args.get('ReplacementChunks', [])
                        })
except Exception as e:
    print(f"Error reading transcript: {e}")

print(f"Found {len(writes)} modifying operations.")
with open('recover_summary.json', 'w') as f:
    json.dump(writes, f, indent=2)
