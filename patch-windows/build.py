"""Build the Windows patch from committed source and verify its manifest."""
import argparse
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

BASE = '136196ebbd75f41a14963296eae285a854fdd7df'
# Include only the application files changed since the shipped 1.2 build.
FILES = tuple(subprocess.check_output([
    'git', 'diff', '--name-only', '--diff-filter=AM', BASE, 'HEAD', '--',
    'moodle/course/view.php', 'moodle/local/seavan', 'moodle/local/seavanmedia',
    'moodle/theme/seavan', 'windows-native/academy.ps1',
], text=True).splitlines())
FOLDER = 'SeavanAcademy_Patch_1.3'
parser = argparse.ArgumentParser()
parser.add_argument('zip', type=Path)
parser.add_argument('--stage-base', action='store_true', help='Restore base source files for the Windows integration test')
args = parser.parse_args()
release = subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip()
manifest = {'base': BASE, 'release': release, 'files': []}
with zipfile.ZipFile(args.zip, 'w', compression=zipfile.ZIP_DEFLATED) as package:
    for path in FILES:
        old = subprocess.run(['git', 'show', f'{BASE}:{path}'], capture_output=True)
        new = subprocess.check_output(['git', 'show', f'{release}:{path}'])
        before = hashlib.sha256(old.stdout).hexdigest() if not old.returncode else ''
        manifest['files'].append({'path': path, 'before': before, 'after': hashlib.sha256(new).hexdigest()})
        package.writestr(f'{FOLDER}/payload/{path}', new)
        if args.stage_base:
            if before:
                Path(path).write_bytes(old.stdout)
            else:
                Path(path).unlink()
    for name in ('APPLY-PATCH.cmd', 'apply-patch.ps1', 'README.fa.md'):
        data = Path('patch-windows', name).read_bytes()
        if name.endswith('.cmd'):
            data = data.replace(b'\r\n', b'\n').replace(b'\n', b'\r\n')
        package.writestr(f'{FOLDER}/{name}', data)
    package.writestr(f'{FOLDER}/manifest.json', json.dumps(manifest, indent=2))
with zipfile.ZipFile(args.zip) as package:
    assert package.testzip() is None
if args.stage_base:
    Path('RELEASE-COMMIT.txt').write_text(BASE + '\n', encoding='ascii')
