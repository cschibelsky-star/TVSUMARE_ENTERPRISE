# HeyGen v3 migration — 2026-10-06

The only HeyGen v1/v2 calls found in the TV Sumaré `main` PHP tree were
`tvp_send_heygen()` and `tvp_check_heygen()` in `includes/heygen_helper.php`.
This change moves them to `POST /v3/videos` and `GET /v3/videos/{video_id}`.

The avatar request uses `type=avatar`, the same configured avatar/voice IDs,
cleaned script, `voice_settings.speed=1`, MP4 and 720p. Landscape/portrait map
to 16:9/9:16. `engine.type=avatar_iii` explicitly keeps the legacy renderer;
v3 otherwise defaults to Avatar IV. Creation reads `data.video_id` (with
resource `id` and flat-response compatibility); status reads the v3 delivery
and failure fields. Output video URLs remain withheld until `completed`.

No changes to configuration/secret resolution, presenter policy,
`tvp_http()`, `outbound_guard.php`, paid generation, runtime, merge or deploy.

## Branch and Factory audit

- `main` baseline: `f5e47eabb5d8a49a0f64ae8d62076886e296f835`.
- VPS Git status reports clean `feature/tvsumare-boletim-social` tracking origin.
  Its helper already uses `/v3/video-agents` plus `/v3/videos/{video_id}` and
  runtime `HEYGEN_API_KEY`. It has no matching legacy calls and is unchanged.
  This PR targets `main`, where the reported legacy calls actually exist;
  it must not replace that operational branch's Video Agent implementation.
- Factory baseline: `f576baa1fa13112405daba2045a92d8cd41ca330`.
  `products/tv-digital-enterprise/includes/heygen_helper.php` contains config
  utilities, not the legacy avatar bridge. The guarded fallback in
  `includes/video_ai_helper.php` uses `/v3/video-agents` and `/v3/videos/{id}`.
  A scan of every PHP file in that product found no HeyGen v1/v2 endpoints.
  No Factory code copy is needed for this migration. Existing differences in
  its config/security and Video Agent schema are not reconciled by this PR.
- GitHub ecosystem searches for HeyGen with `/v1/` and `/v2/` found the same
  two TV Sumaré calls; other hits concern different providers. Core's
  `app/Services/Heygen/HeygenService.php` already uses `/v3/videos`.

## Validation

`php -n tests/heygen_v3_http_test.php` runs the production helper with fake
cURL and isolated configuration. No actual HTTP transport or API key is used.
It covers payloads, configured presenter precedence, script cleaning,
orientations, response IDs, encoded existing IDs, pending/completed/failed
states, delivery URLs, HTTP errors, malformed/oversized responses, missing
key/ID/script and outbound denial. It fails against the baseline at the v3
endpoint assertion. CI runs it before Docker and again after all Docker
applicators, guarding against a build-time regression to v1/v2.

Local PHP 8.2: 66 simulated-HTTP assertions, editorial policy tests and lint
of 134 PHP files passed. CI uses PHP 8.3 and the full Docker build/smoke.
A real generation is intentionally not part of validation.

## Official references

- https://developers.heygen.com/endpoint-version-comparison
- https://developers.heygen.com/reference/create-video
- https://developers.heygen.com/reference/create-video.md (OpenAPI engine schema)
- https://developers.heygen.com/reference/get-video
