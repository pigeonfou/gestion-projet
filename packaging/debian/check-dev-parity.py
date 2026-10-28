#!/usr/bin/env python3
"""Compare all runtime files to the explicitly approved, deployed DEV commit."""
import hashlib,json,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[2]
baseline=json.loads((root/'packaging/debian/dev-baseline.json').read_text())
def runtime(path):
    return path.startswith(('actions/','admin/','assets/','config/','includes/','install/')) or ('/' not in path and (path.endswith('.php') or path=='.htaccess')) or path in ['requirements-cdc-msg.txt','scripts/generate-cdc-msg.py']
def tree(ref):
    paths=subprocess.check_output(['git','ls-tree','-rz','--name-only',ref],cwd=root).decode().strip('\0').split('\0')
    return {p for p in paths if runtime(p)}
def digest(data):return hashlib.sha256(data).hexdigest()
def content(ref,path):return subprocess.check_output(['git','show',ref+':'+path],cwd=root)
expected=tree(baseline['commit']);actual=tree('HEAD')
if actual!=expected:raise SystemExit('Runtime files differ from DEV: '+str(sorted(actual^expected)))
for path in sorted(expected):
    dev=content(baseline['commit'],path);pack=(root/path).read_bytes()
    if dev==pack:continue
    adaptation=baseline['adaptations'].get(path)
    if not adaptation or digest(dev)!=adaptation['dev_sha256'] or digest(pack)!=adaptation['packaging_sha256']:
        raise SystemExit('Unapproved runtime difference: '+path)
print('Runtime parity verified:',len(expected),'files; approved DEV commit',baseline['commit'])
