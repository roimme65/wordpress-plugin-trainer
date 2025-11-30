#!/usr/bin/env python3
"""Test the ZIP extractor logic used by scripts/import-plugin.sh

Creates two zips: one with Windows-style backslashes in member names and one with normal forward-slash names.
Runs the same extraction logic and verifies normalized paths are created.
"""
import os
import tempfile
import zipfile
import shutil


def make_zip(path, entries):
    with zipfile.ZipFile(path, 'w') as z:
        for name, contents in entries.items():
            # write a file entry with the given archive name
            z.writestr(name, contents)


def extract_normalize(src, out):
    with zipfile.ZipFile(src) as z:
        for info in z.infolist():
            name = info.filename.replace('\\', '/')
            name = name.lstrip('/\\')
            target = os.path.join(out, name)
            if name.endswith('/'):
                os.makedirs(target, exist_ok=True)
            else:
                os.makedirs(os.path.dirname(target), exist_ok=True)
                with z.open(info) as r, open(target, 'wb') as w:
                    shutil.copyfileobj(r, w)


def run_test():
    tmp = tempfile.mkdtemp(prefix='extract-test-')
    try:
        # create a backslash zip
        bz = os.path.join(tmp, 'backslashes.zip')
        entries = {
            'my-plugin\\assets\\css\\style.css': 'body {}',
            'my-plugin\\README.md': 'readme',
        }
        make_zip(bz, entries)

        out_b = os.path.join(tmp, 'out_back')
        os.makedirs(out_b)
        extract_normalize(bz, out_b)

        assert os.path.exists(os.path.join(out_b, 'my-plugin', 'assets', 'css', 'style.css'))
        assert os.path.exists(os.path.join(out_b, 'my-plugin', 'README.md'))

        # create a forward-slash zip
        fz = os.path.join(tmp, 'slashes.zip')
        entries2 = {
            'my-plugin/assets/css/style2.css': 'h1 {}',
            'my-plugin/README2.md': 'readme2',
        }
        make_zip(fz, entries2)
        out_f = os.path.join(tmp, 'out_forw')
        os.makedirs(out_f)
        extract_normalize(fz, out_f)

        assert os.path.exists(os.path.join(out_f, 'my-plugin', 'assets', 'css', 'style2.css'))
        assert os.path.exists(os.path.join(out_f, 'my-plugin', 'README2.md'))

        print('OK')
    finally:
        shutil.rmtree(tmp)


if __name__ == '__main__':
    run_test()
