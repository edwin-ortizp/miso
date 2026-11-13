"""Compile a snapshot when a local --watch process shares the normal candidate folder.

Only compiler-published HTML is copied back; no generated document is edited.
"""
from pathlib import Path
import argparse
import json
import shutil
import subprocess
import sys
import tempfile

repo = Path(__file__).resolve().parents[5]
project = repo / 'docs/mockup-system'
engine = repo.parent / 'mockup-system/build.py'
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--all', action='store_true')
args = parser.parse_args()
with tempfile.TemporaryDirectory(prefix='miso-formation-review-') as temporary:
    snapshot = Path(temporary)
    config = json.loads((project / 'mockup-system.project.json').read_text(encoding='utf-8'))
    shutil.copy2(project / 'mockup-system.project.json', snapshot / 'mockup-system.project.json')
    for key, relative in config['paths'].items():
        if key != 'dist' and (project / relative).is_dir():
            shutil.copytree(project / relative, snapshot / relative)
    (snapshot / config['paths']['dist']).mkdir(parents=True, exist_ok=True)
    command = [sys.executable, str(engine), '--project', str(snapshot)]
    command += ['--all'] if args.all else ['learning']
    subprocess.run(command, cwd=repo, check=True)
    published = snapshot / config['paths']['dist']
    destination = project / config['paths']['dist']
    files = list(published.glob('*.html'))
    for artifact in files:
        shutil.copy2(artifact, destination / artifact.name)
    print(f'Published {len(files)} compiler-verified HTML artifacts from isolated snapshot.')
