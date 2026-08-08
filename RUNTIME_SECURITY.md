# Container runtime hardening

- Root filesystem is read-only.
- Persistent writes are limited to data, uploads, videos and logs bind mounts.
- Apache runtime directories and PHP sessions use size-limited tmpfs mounts.
- no-new-privileges is enabled.
- All Linux capabilities are dropped except SETGID, SETUID and NET_BIND_SERVICE.
- PID limit: 128.
- Memory limit: 512 MiB.
- CPU limit: 1 core.
- Open files: soft 1024, hard 4096.
- Uploads and videos cannot execute PHP or script extensions.
- Data and logs are denied at the Apache configuration layer.
- Apache server tokens, signatures, TRACE and file ETags are disabled.
