# Local functional QA

Date: 2026-08-03
Environment: isolated Docker network, no external access

## Read-only crawl

- 24 internal pages and assets requested
- 24 HTTP 200 responses
- Zero broken links
- Zero HTTP 404 or 5xx responses
- Zero PHP runtime errors
- Production-like data mounted read-only

## Writable-flow crawl

- Disposable copy of JSON data used
- 31 internal pages and assets requested
- 10 article pages exercised
- 31 HTTP 200 responses
- Zero broken links
- Zero HTTP 404 or 5xx responses
- Zero PHP runtime errors
- Temporary container and data copy removed

## Main service after QA

- Health: healthy
- Restart count: 0
- Network: internal
- Published ports: none
- Real persistent data was not used for write-flow tests
