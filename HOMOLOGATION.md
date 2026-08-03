# Homologation baseline

Snapshot source:

- /srv/tvsumare/snapshots/hostgator/20260803-005551
- 162 files, 17 directories, 50,813,688 bytes
- Source remained read-only

Runtime:

- PHP 8.3 with Apache
- JSON file storage mounted from /srv/tvsumare/shared/data
- Uploads, videos and logs stored under /srv/tvsumare/shared
- Docker network tvsumare_internal is internal
- No host port, vhost or DNS publication

Validation:

- PHP lint: 97 files, zero errors
- Compose config: valid
- Secret signature scan: zero findings
- HTTP 200: /health.php, /, /noticias.php, /videos.php, /admin/login.php
- Admin credentials are empty by default and cannot authenticate

Next:

1. Review application behavior and content in the isolated container.
2. Configure dedicated homologation secrets.
3. Perform security review of administrative and callback endpoints.
4. Only then connect to the reverse proxy and homologation domain.
