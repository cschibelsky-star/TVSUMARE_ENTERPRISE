#!/usr/bin/env python3
"""Synthetic regression tests. No operational archive or API keys are used."""
import hashlib
import io
import os
from pathlib import Path
import subprocess
import sys
import tarfile
import tempfile

script = Path(__file__).resolve().parents[1] / "tools" / "tvsumare_backup_sample_restore.py"

def add(tf, name, payload):
    info = tarfile.TarInfo(name)
    info.size = len(payload)
    tf.addfile(info, io.BytesIO(payload))

def invoke(archive, digest):
    return subprocess.run(
        [sys.executable, str(script), "--archive", str(archive), "--expected-sha256", digest],
        capture_output=True, text=True, check=False
    )

with tempfile.TemporaryDirectory(prefix="tvsumare-archive-test-") as temp:
    folder = Path(temp)
    archive = folder / "vps-backup.tar.gz"
    with tarfile.open(archive, "w:gz") as tf:
        add(tf, "srv/tvsumare/shared/data/fontes.json", b'[{"name":"synthetic"}]')
        add(tf, "srv/tvsumare/shared/data/noticias.json", b"[]")
        add(tf, "srv/tvsumare/shared/data/other.txt", b"not selected")
        add(tf, "srv/projects/other/sample.json", b"[]")
    expected = hashlib.sha256(archive.read_bytes()).hexdigest()
    result = invoke(archive, expected)
    assert result.returncode == 0 and "PASS_SAMPLED_ONLY" in result.stdout, (result.returncode, result.stdout, result.stderr)
    assert "RECOVERED_JSON_SAMPLES=2" in result.stdout, result.stdout
    result = invoke(archive, "0" * 64)
    assert result.returncode == 1 and "checksum_mismatch" in result.stdout, (result.returncode, result.stdout, result.stderr)

    with tarfile.open(archive, "w:gz") as tf:
        add(tf, "srv/tvsumare/shared/data/fontes.json", b"{broken")
    expected = hashlib.sha256(archive.read_bytes()).hexdigest()
    result = invoke(archive, expected)
    assert result.returncode == 1 and "restored_json_invalid" in result.stdout, (result.returncode, result.stdout, result.stderr)

    with tarfile.open(archive, "w:gz") as tf:
        add(tf, "srv/projects/other/file.txt", b"unrelated")
    expected = hashlib.sha256(archive.read_bytes()).hexdigest()
    result = invoke(archive, expected)
    assert result.returncode == 2 and "INCONCLUSIVE" in result.stdout, (result.returncode, result.stdout, result.stderr)

    with tarfile.open(archive, "w:gz") as tf:
        info = tarfile.TarInfo("srv/tvsumare/shared/data/fontes.json")
        info.type = tarfile.SYMTYPE
        info.linkname = "/srv/tvsumare/shared/data/fontes.json"
        tf.addfile(info)
    expected = hashlib.sha256(archive.read_bytes()).hexdigest()
    result = invoke(archive, expected)
    assert result.returncode == 1 and "unsafe_member_type" in result.stdout, (result.returncode, result.stdout, result.stderr)

print("TVSUMARE_BACKUP_SAMPLE_RESTORE_TEST=PASS")
