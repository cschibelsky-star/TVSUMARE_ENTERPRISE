#!/usr/bin/env python3
"""Validate the rendered Docker Compose HML isolation configuration, no Docker runtime access."""
import json
import sys

config = json.load(sys.stdin)
services = config.get("services", {})
expected = {"data", "uploads", "videos", "logs"}
for name, kinds in (("web", expected), ("radar_scheduler", expected - {"videos"})):
    svc = services.get(name)
    if not svc:
        raise SystemExit(f"FAIL: {name} service missing")
    found = set()
    for mount in svc.get("volumes", []):
        target = mount.get("target", "")
        if not target.startswith("/var/www/html/"):
            continue
        kind = target.removeprefix("/var/www/html/")
        if kind not in expected:
            continue
        found.add(kind)
        if mount.get("type") != "bind":
            raise SystemExit(f"FAIL: {name}.{kind} not bind")
        if mount.get("source") != f"/srv/tvsumare/hml-isolated/{kind}":
            raise SystemExit(f"FAIL: {name}.{kind} not isolated")
        if mount.get("bind", {}).get("create_host_path") is not False:
            raise SystemExit(f"FAIL: {name}.{kind} does not fail closed")
    if found != kinds:
        raise SystemExit(f"FAIL: {name} volumes mismatch {found}")

scheduler = services["radar_scheduler"]
if "hml-radar-opt-in" not in scheduler.get("profiles", []):
    raise SystemExit("FAIL: Radar scheduler must be opt-in")
for name in ("web", "radar_scheduler"):
    svc = services[name]
    env = svc.get("environment", {})
    if env.get("TVSUMARE_ENV") != "homologation":
        raise SystemExit(f"FAIL: {name} not marked HML")
    for key in ("OPENAI_API_KEY", "HEYGEN_API_KEY", "SITE_FACTORY_TOKEN",
                "TVSUMARE_WHATSAPP_WEBHOOK_URL", "TVSUMARE_OUTBOUND_HOSTS"):
        if env.get(key, ""):
            raise SystemExit(f"FAIL: {name} external credential/side effect enabled: {key}")
print("TVSUMARE_HML_COMPOSE_ISOLATION_TEST=PASS")
