#!/usr/bin/env python3
"""Read-only backup audit + sampled restore into a disposable private directory.

Never extracts arbitrary archive entries or writes into TV Sumare live folders.
A pass proves hash integrity and recovery of selected JSON samples, NOT full restore.
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import sys
import tarfile
import tempfile

CANDIDATES = {"fontes.json", "noticias.json", "materias_aprovacao.json"}
PREFIX = "srv/tvsumare/shared/data/"
MAX_SAMPLE = 8 * 1024 * 1024
CHUNK = 1024 * 1024


def report(status, reason):
    print(f"TVSUMARE_BACKUP_RESTORE_GATE={status} REASON={reason}")
    return {"PASS_SAMPLED_ONLY": 0, "INCONCLUSIVE": 2}.get(status, 1)


def normalized(name):
    while name.startswith("./"):
        name = name[2:]
    return name


def verify(archive, expected_sha256):
    if not re.fullmatch(r"[a-fA-F0-9]{64}", expected_sha256):
        return report("FAIL", "invalid_sha256_argument")
    if not archive.is_file() or archive.is_symlink():
        return report("FAIL", "archive_unavailable_or_symlink")
    digest = hashlib.sha256()
    try:
        with archive.open("rb") as source:
            for block in iter(lambda: source.read(4 * CHUNK), b""):
                digest.update(block)
    except OSError:
        return report("FAIL", "archive_read_failed")
    if digest.hexdigest() != expected_sha256.lower():
        return report("FAIL", "archive_checksum_mismatch")
    print("ARCHIVE_SHA256=PASS")
    restored = set()
    skipped_large = 0
    try:
        # The exclusive tmp directory has mode 0700; samples are never printed.
        with tempfile.TemporaryDirectory(prefix="tvsumare-restore-", dir="/var/tmp") as folder:
            with tarfile.open(archive, mode="r|gz") as bundle:
                for entry in bundle:
                    member = normalized(entry.name)
                    if not member.startswith(PREFIX):
                        continue
                    filename = member[len(PREFIX):]
                    if filename not in CANDIDATES:
                        continue
                    if filename in restored:
                        return report("FAIL", "duplicate_backup_member")
                    if not entry.isfile():
                        return report("FAIL", "unsafe_member_type")
                    if entry.size > MAX_SAMPLE:
                        skipped_large += 1
                        continue
                    reader = bundle.extractfile(entry)
                    if reader is None:
                        return report("FAIL", "sample_not_readable")
                    destination = Path(folder) / filename
                    src_digest = hashlib.sha256()
                    fd = os.open(destination, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600)
                    with os.fdopen(fd, "wb") as output:
                        remaining = entry.size
                        while remaining:
                            block = reader.read(min(CHUNK, remaining))
                            if not block:
                                return report("FAIL", "sample_truncated")
                            output.write(block)
                            src_digest.update(block)
                            remaining -= len(block)
                    with destination.open("rb") as recovered:
                        raw = recovered.read(MAX_SAMPLE + 1)
                    if hashlib.sha256(raw).digest() != src_digest.digest():
                        return report("FAIL", "sample_restore_checksum_mismatch")
                    try:
                        value = json.loads(raw.decode("utf-8-sig"))
                    except (UnicodeError, ValueError):
                        return report("FAIL", "restored_json_invalid")
                    if not isinstance(value, (dict, list)):
                        return report("FAIL", "restored_json_wrong_type")
                    restored.add(filename)
    except (OSError, tarfile.TarError) as exc:
        return report("FAIL", "archive_or_restore_io_error")
    print(f"RECOVERED_JSON_SAMPLES={len(restored)} SKIPPED_OVERSIZE={skipped_large}")
    if not restored:
        return report("INCONCLUSIVE", "no_suitable_json_samples")
    return report("PASS_SAMPLED_ONLY", "full_restore_not_proven")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--archive", type=Path, required=True)
    parser.add_argument("--expected-sha256", required=True)
    args = parser.parse_args()
    sys.exit(verify(args.archive, args.expected_sha256))
