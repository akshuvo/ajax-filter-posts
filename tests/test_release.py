"""Failure boundaries using disposable Git and SVN repositories; never production."""

import importlib.util
from pathlib import Path
import shutil
import tempfile
import unittest
from unittest.mock import patch
import zipfile

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('release', ROOT / 'scripts/release.py')
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class ReleaseTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.base = Path(self.temporary.name)
        self.source = self.base / 'source'
        self.source.mkdir()
        self.output = self.base / 'output'
        for name in release.inventory(ROOT):
            destination = self.source / name
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT / name, destination)
        (self.source / '.github').mkdir()
        for name in ('release-files.txt', 'release-baseline.json', 'release-removals.json'):
            shutil.copyfile(ROOT / '.github' / name, self.source / '.github' / name)
        release.run('git', 'init', '-q', cwd=self.source)
        release.run('git', 'config', 'user.email', 'test@example.invalid', cwd=self.source)
        release.run('git', 'config', 'user.name', 'Release test', cwd=self.source)
        release.run('git', 'add', '.', cwd=self.source)
        release.run('git', 'commit', '-qm', 'fixture', cwd=self.source)

    def tearDown(self):
        self.temporary.cleanup()

    def build(self):
        release.build(self.source, self.output)

    def test_complete_recursive_reproducible_package(self):
        self.build()
        release.build(self.source, self.base / 'second')
        self.assertEqual((self.output / 'ajax-filter-posts.zip').read_bytes(),
                         (self.base / 'second/ajax-filter-posts.zip').read_bytes())
        self.assertEqual(set(release.manifest(self.output / release.SLUG)),
                         set(release.inventory(self.source)))

    def test_missing_nested_file_aborts(self):
        (self.source / 'inc/functions.php').unlink()
        with self.assertRaisesRegex(RuntimeError, 'Missing/empty'):
            self.build()

    def test_unlisted_runtime_file_aborts(self):
        (self.source / 'inc/new.php').write_text('<?php')
        release.run('git', 'add', '.', cwd=self.source)
        with self.assertRaisesRegex(RuntimeError, 'inventory'):
            self.build()

    def test_version_mismatch_aborts(self):
        (self.source / 'package.json').write_text('{"version":"0.0.1"}')
        with self.assertRaisesRegex(RuntimeError, 'disagree'):
            self.build()

    def test_wrong_tag_and_dirty_release_abort(self):
        with self.assertRaisesRegex(RuntimeError, 'Tag must match'):
            release.build(self.source, self.output, 'v0.0.1')
        (self.source / 'inc/functions.php').write_text('<?php // dirty')
        with self.assertRaisesRegex(RuntimeError, 'must be clean'):
            release.build(self.source, self.output, 'v' + release.version(ROOT))

    def test_symlink_and_gitlink_rejected(self):
        path = self.source / 'inc/functions.php'
        path.unlink()
        path.symlink_to(ROOT / 'inc/functions.php')
        release.run('git', 'add', '.', cwd=self.source)
        with self.assertRaisesRegex(RuntimeError, 'Gitlink/symlink'):
            self.build()
        path.unlink()
        shutil.copyfile(ROOT / 'inc/functions.php', path)
        release.run('git', 'rm', '-r', '--cached', 'appsero', cwd=self.source)
        commit = release.run('git', 'rev-parse', 'HEAD', cwd=self.source).strip()
        release.run('git', 'update-index', '--add', '--cacheinfo', '160000,' + commit + ',appsero', cwd=self.source)
        with self.assertRaisesRegex(RuntimeError, 'Gitlink/symlink'):
            self.build()

    def test_unexplained_deletion_rejected(self):
        with self.assertRaisesRegex(RuntimeError, 'unexplained deletions'):
            release.check_deletions({'inc/nested.php': 'hash'}, {}, set(), 'test')
        self.assertEqual(release.check_deletions({'old.php': 'hash'}, {}, {'old.php'}, 'test'), {'old.php'})

    def test_zip_roots_traversal_duplicates_and_missing_nested_file(self):
        for names in [['wrong-root/file.php'], ['ajax-filter-posts/../escape'],
                      ['ajax-filter-posts/file.php', 'ajax-filter-posts/file.php']]:
            path = self.base / 'bad.zip'
            with zipfile.ZipFile(path, 'w') as archive:
                for name in names:
                    archive.writestr(name, 'bad')
            with self.assertRaises(RuntimeError):
                release.zip_manifest(path)
        self.build()
        with self.assertRaisesRegex(RuntimeError, 'missing='):
            release.compare(release.manifest(self.output / release.SLUG),
                            {'ajax-filter-posts.php': 'bad'}, 'incomplete archive')

    def local_svn_stage(self):
        self.build()
        wc = self.output / 'svn'
        repository = self.base / 'repository'
        release.run('svnadmin', 'create', str(repository))
        url = repository.as_uri()
        release.run('svn', 'mkdir', url + '/trunk', url + '/tags', url + '/assets', '-m', 'initial')
        release.run('svn', 'checkout', '-q', url, str(wc))
        shutil.copytree(self.output / release.SLUG, wc / 'trunk', dirs_exist_ok=True)
        release.run('svn', 'add', '--force', 'trunk', cwd=wc)
        release.run('svn', 'commit', '-qm', 'baseline', cwd=wc)
        before = release.manifest(wc / 'trunk')
        release.run('svn', 'copy', 'trunk', 'tags/' + release.version(self.source), cwd=wc)
        state = {'version': release.version(self.source), 'baseline': before,
                 'before_trunk': before, 'assets': {}}
        release.write_json(self.output / 'stage.json', state)
        return wc

    def test_local_svn_accepts_complete_tag_rejects_tampering(self):
        wc = self.local_svn_stage()
        release.validate_stage(self.source, self.output)
        (wc / 'trunk/inc/functions.php').unlink()
        with self.assertRaisesRegex(RuntimeError, 'Staged trunk: missing='):
            release.validate_stage(self.source, self.output)

    def test_local_svn_rejects_listing_asset_changes(self):
        wc = self.local_svn_stage()
        (wc / 'assets/unexpected.txt').write_text('wrong target')
        with self.assertRaisesRegex(RuntimeError, 'Listing assets'):
            release.validate_stage(self.source, self.output)

    def test_changed_baseline_hash_rejected(self):
        with self.assertRaisesRegex(RuntimeError, 'changed='):
            release.compare({'inc/deep/file.php': 'a'}, {'inc/deep/file.php': 'b'}, 'baseline')

    def test_publish_denied_outside_actions(self):
        with patch.dict('os.environ', {'GITHUB_ACTIONS': 'false'}):
            with self.assertRaisesRegex(RuntimeError, 'only from GitHub Actions'):
                release.publish(self.source, self.output, 'v0.0.0')

    def prepare_publish(self):
        self.local_svn_stage()
        state = release.read_json(self.output / 'stage.json')
        state.update(root_changed=1, change_digest=release.validate_stage(self.source, self.output))
        release.write_json(self.output / 'stage.json', state)
        data = release.read_json(self.output / 'build.json')
        data['tag'] = 'v' + state['version']
        release.write_json(self.output / 'build.json', data)
        return state

    def test_changed_remote_aborts_before_commit(self):
        state = self.prepare_publish()
        with patch.dict('os.environ', {'GITHUB_ACTIONS': 'true', 'SVN_USERNAME': 'fixture', 'SVN_PASSWORD': 'fixture'}):
            with patch.object(release, 'svn_info', return_value=(9, 2)):
                with self.assertRaisesRegex(RuntimeError, 'Remote SVN changed'):
                    release.publish(self.source, self.output, 'v' + state['version'])

    def test_changed_stage_digest_aborts_before_commit(self):
        state = self.prepare_publish()
        state['change_digest'] = 'unapproved'
        release.write_json(self.output / 'stage.json', state)
        with patch.dict('os.environ', {'GITHUB_ACTIONS': 'true', 'SVN_USERNAME': 'fixture', 'SVN_PASSWORD': 'fixture'}):
            with self.assertRaisesRegex(RuntimeError, 'changed after validation'):
                release.publish(self.source, self.output, 'v' + state['version'])

    def test_existing_tag_is_immutable_and_validation_only_checks_hashes(self):
        wc = self.local_svn_stage()
        release.run('svn', 'commit', '-qm', 'publish fixture tag', cwd=wc)
        output = self.base / 'existing-check'
        release.build(self.source, output)
        with patch.object(release, 'SVN_URL', (self.base / 'repository').as_uri()):
            with self.assertRaisesRegex(RuntimeError, 'already exists'):
                release.stage(self.source, output)
            release.stage(self.source, output, check_existing=True)
            self.assertFalse((output / 'stage.json').exists())
            self.assertTrue((output / 'existing-release.json').exists())


if __name__ == '__main__':
    unittest.main()
