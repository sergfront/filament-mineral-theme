"""Regression checks for archive boundaries and safe identity changes."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]

@unittest.skipUnless((ROOT / 'bin/package').exists(), 'Release tooling belongs to the Pro source')
class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name) / 'project'
        self.root.mkdir()
        for name in ['bin', 'src', 'resources', 'config']:
            shutil.copytree(ROOT / name, self.root / name)
        for name in ['release.json', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'README.md']:
            shutil.copy2(ROOT / name, self.root / name)
        self.lite = Path(self.temp.name) / 'lite'
        lite_files = ['release.json', 'README.md', 'src/MineralThemePlugin.php',
                      'src/MineralThemeServiceProvider.php', 'src/Enums/Palette.php',
                      'src/Enums/Density.php', 'src/Enums/SidebarStyle.php',
                      'config/mineral-theme.php', 'resources/views/marker.blade.php',
                      'resources/views/styles.blade.php', 'resources/css/palettes/default.css']
        for name in lite_files:
            target = self.lite / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / name, target)
        (self.lite / 'src/Edition.php').write_text('<?php class Edition { const PRO = false; }')
        (self.lite / 'resources/dist').mkdir(parents=True)
        (self.lite / 'resources/dist/mineral.css').write_text('body:has(>[data-mineral-theme=default]) { color: #000; }')
        shutil.copy2(ROOT / 'LICENSE-LITE.md', self.lite / 'LICENSE.md')
        composer = json.loads((ROOT / 'composer.json').read_text())
        composer['license'] = 'MIT'
        (self.lite / 'composer.json').write_text(json.dumps(composer))

    def pack(self):
        subprocess.run(['python3', 'bin/package', '--lite', str(self.lite), '--output', 'releases'], cwd=self.root, check=True, capture_output=True)
        name = json.loads((self.root / 'release.json').read_text())['package']
        with ZipFile(self.root / 'releases' / (name + '-pro-preview.zip')) as z:
            return {n.split('/', 1)[1] for n in z.namelist()}

    def test_archives_exclude_secrets_and_previous_outputs(self):
        for name in ['.env', '.env.production', '.env.backup', 'auth.json', 'src/.env.production']:
            (self.root / name).write_text('REVIEW_DUMMY_SECRET')
        (self.root / 'notes-private.txt').write_text('REVIEW_DUMMY_SECRET')
        (self.root / 'releases').mkdir()
        (self.root / 'releases/old.zip').write_text('old artifact')
        first = self.pack()
        second = self.pack()
        self.assertEqual(first, second)
        self.assertIn('src/MineralThemePlugin.php', first)
        self.assertFalse(any('.env' in n or 'auth.json' in n or n.startswith('releases/') or n == 'notes-private.txt' for n in first))

    def test_output_in_parent_directory_keeps_source_files(self):
        subprocess.run(['python3', 'bin/package', '--lite', str(self.lite), '--output', '..'], cwd=self.root, check=True, capture_output=True)
        name = json.loads((self.root / 'release.json').read_text())['package']
        with ZipFile(self.root.parent / (name + '-pro-preview.zip')) as z:
            self.assertTrue(any(n.endswith('/src/MineralThemePlugin.php') for n in z.namelist()))

    def test_incomplete_lite_fails_before_writing_archives(self):
        (self.lite / 'composer.json').unlink()
        output = self.root / 'releases'
        output.mkdir()
        previous = output / 'existing.zip'
        previous.write_bytes(b'previous archive')
        result = subprocess.run(['python3', 'bin/package', '--lite', str(self.lite), '--output', str(output)], cwd=self.root, capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Incomplete Lite export: composer.json', result.stderr)
        self.assertEqual(previous.read_bytes(), b'previous archive')
        self.assertEqual(sorted(p.name for p in output.iterdir()), ['existing.zip'])

    def test_pro_css_in_lite_fails_before_writing_archives(self):
        css = self.lite / 'resources/dist/mineral.css'
        css.write_text(css.read_text() + ' body:has(>[data-sidebar=floating]) {}')
        output = self.root / 'releases'
        result = subprocess.run(['python3', 'bin/package', '--lite', str(self.lite), '--output', str(output)], cwd=self.root, capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('Pro styles found in Lite CSS', result.stderr)
        self.assertFalse(output.exists())

    def test_rename_does_not_inject_email_into_javascript(self):
        tools = Path(self.temp.name) / 'tools'
        tools.mkdir()
        composer = tools / 'composer'
        composer.write_text('#!/bin/sh\nexit 0\n')
        composer.chmod(0o755)
        env = {**os.environ, 'PATH': str(tools) + os.pathsep + os.environ['PATH']}
        email = "o'hara@example.com"
        subprocess.run(['python3', 'bin/rename', '--vendor', 'example-vendor', '--package', 'filament-renamed-theme', '--email', email], cwd=self.root, env=env, check=True, capture_output=True)
        subprocess.run(['node', '--check', 'bin/build-lite.mjs'], cwd=self.root, check=True, capture_output=True)
        identity = json.loads((self.root / 'release.json').read_text())
        self.assertEqual(identity['support_email'], email)
        self.assertEqual(json.loads((self.root / 'composer.json').read_text())['name'], 'example-vendor/filament-renamed-theme')
        self.assertNotIn(email, (self.root / 'bin/build-lite.mjs').read_text())

    def test_failed_composer_does_not_partially_rename(self):
        tools = Path(self.temp.name) / 'tools'
        tools.mkdir()
        composer = tools / 'composer'
        composer.write_text('#!/bin/sh\nexit 42\n')
        composer.chmod(0o755)
        env = {**os.environ, 'PATH': str(tools) + os.pathsep + os.environ['PATH']}
        files = ['release.json', 'composer.json', 'composer.lock', 'package.json',
                 'package-lock.json', 'README.md']
        before = {name: (self.root / name).read_bytes() for name in files}
        result = subprocess.run(['python3', 'bin/rename', '--vendor', 'acme',
                                 '--package', 'new-name', '--email', 'audit+test@example.test'],
                                cwd=self.root, env=env, capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(before, {name: (self.root / name).read_bytes() for name in files})

if __name__ == '__main__':
    unittest.main()
