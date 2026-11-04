#!/usr/bin/env python3
"""Fail on any packaged PHP syntax error (including bundled dependencies)."""
from pathlib import Path
import subprocess
import sys

files = sorted(Path(sys.argv[1]).rglob('*.php'))
if not files:
    sys.exit('No packaged PHP files found')
for path in files:
    subprocess.run(['php', '-l', str(path)], check=True)
print(f'PASS: {len(files)} packaged PHP files parsed')
