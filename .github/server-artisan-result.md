# Server Artisan Result

- Time: 2026-10-06 08:49:33 UTC
- Task: `health-check`
- Artisan args: `none`
- Log file: `storage/logs/laravel.log`
- Exit code: `0`

```text
No local changes to save
From https://github.com/Cowastro/Kotlov
   6287caad..5e433892  main       -> origin/main
Updating 6287caad..5e433892
Fast-forward
 .github/server-artisan-result.md           | 37 +++++++++++++++++++++---------
 .github/server-artisan-task.json           |  2 +-
 .github/workflows/server-artisan-queue.yml |  4 ++++
 3 files changed, 31 insertions(+), 12 deletions(-)
== Public HTTPS ==
curl: (60) SSL certificate problem: unable to get local issuer certificate
More details here: https://curl.se/docs/sslcerts.html

curl failed to verify the legitimacy of the server and therefore could not
establish a secure connection to it. To learn more about this situation and
how to fix it, please visit the web page mentioned above.
== Public HTTPS without certificate verification ==
HTTP/2 200 
server: nginx/1.30.4
date: Tue, 06 Oct 2026 08:49:33 GMT
content-type: text/html; charset=utf-8
x-powered-by: PHP/8.3.33
cache-control: no-cache, private
set-cookie: XSRF-TOKEN=eyJpdiI6IlZRTTd3UERNbEkyVndUSmtJQ1JKWEE9PSIsInZhbHVlIjoiZjF6T2lpUmNXeUJ4Mzh0cHJEb0RFTTh4amNBNmoxSVB1dCtxazU4L3JXdU03c0hMTXVURzdGTmtOc2dMNGRuY0E3OElSeGQ0NkRsZmxjY2xRanNrQVhHbTF2SmNOZVFnbzY2TWZyNkFyaTNFRzRnenpOaVFHZzBFSmVFd2tmczgiLCJtYWMiOiJhMjZiNWMxZWE5N2FiNWIyZDAxOTFjNDZkMGNiZjQ4MGYzMDU5MjdjZTZkOTYzODU5ZDZkYWJhMGMyMGYwNTUwIiwidGFnIjoiIn0%3D; expires=Tue, 06 Oct 2026 10:49:33 GMT; Max-Age=7200; path=/; secure; samesite=lax
set-cookie: kotlov-session=eyJpdiI6IlF3NHB1QWFsTVJmTmFlL0s3Wk1HZ3c9PSIsInZhbHVlIjoiTFlYUkxQd2hQZDVIeWZ0bHVkWWEzSmlEbzh3dVZJbXJVQWI4dmY2aHV1UUhjQlIrV1VDQnVYLzBXZjEvbFlIODFmZnhDMEw3RUlwZ09mREJ0ZzlBS3Q0eWZ2SmlDK2E5SituR0c5Z0JVTi9lRG9yQWo1Qzh3MnhacXpZeVFYQnoiLCJtYWMiOiJkYmI4ZDdjOTdlM2NmZGI2YjJhMTEwNTgxYTExMjg2NTAyNmY5YWFlODFhMzA1M2FjMjAzZWJhNTcxMDg2NmVhIiwidGFnIjoiIn0%3D; expires=Tue, 06 Oct 2026 10:49:33 GMT; Max-Age=7200; path=/; secure; httponly; samesite=lax

== Certificate ==
subject=CN=*.kotlov.by
issuer=C=BE, O=GlobalSign nv-sa, CN=GlobalSign GCC R46 AlphaSSL CA 2025
notBefore=Sep  9 06:29:11 2026 GMT
notAfter=Mar 27 06:29:11 2027 GMT
== Served certificate count ==
2
== Nginx certificate configuration ==
== Local HTTP with Host header ==
HTTP/1.1 200 OK
Server: nginx/1.30.4
Date: Tue, 06 Oct 2026 08:49:33 GMT
Content-Type: text/html; charset=utf-8
Connection: keep-alive
X-Powered-By: PHP/8.3.33
Cache-Control: no-cache, private
Set-Cookie: XSRF-TOKEN=eyJpdiI6IndVcWFUOUN4cW9Bd1FJNEN4SmVuSUE9PSIsInZhbHVlIjoiay8yUjZQNFZPUWMxSWY4d1NWd3VZenBlNGRacnNkaE1aNWVtR3RZUXBRbnY2NFlFQ2djM3FHM29Qa3phTzd1dXlwZW5hNnRFLytaMkgxSkgwVkFNT0JBSFVEMjdlN1hNbDJKbGc5aWVRNThXN1YrVUkrNFV1U1hQNUZLTzcrVXkiLCJtYWMiOiI3ZDlkMmZkNjFkZjM5NWRiZGI4MzM1NmE0ZGIxNDVjYTc0OGQ2ZDU3MWZmMTJkNjlkZjVlNzRjYmM4YWU0NzZkIiwidGFnIjoiIn0%3D; expires=Tue, 06 Oct 2026 10:49:33 GMT; Max-Age=7200; path=/; samesite=lax
Set-Cookie: kotlov-session=eyJpdiI6InhaSnlyUVpyRm5FNUpBOGUyWHduSlE9PSIsInZhbHVlIjoiQjFqTDBtS2xWS3pRQitTN3dmRDhtYXhaeFpmZ3NSWHFieW1JM2IzOEpZSG1UbTlzY0hlUkU4d3lGQzV2ME9YZlQzQjhhK2xzRWNlZzByQzh3MTRDeVRUamZrZ3dtR0grcExweVBaRnVFWUZieGN5ZHNYRkYwK0krTmRGYnJ3UlciLCJtYWMiOiJiODZiNmExMzkzZGMwY2RlOTE3NmViZmQyOGEyMWUwNzc3YzNmZjU4NjI0MzVmMTZjYmY3ZjY5NjE5NGE4OTUyIiwidGFnIjoiIn0%3D; expires=Tue, 06 Oct 2026 10:49:33 GMT; Max-Age=7200; path=/; httponly; samesite=lax

== Web/PHP processes ==
h209767   969310       1  0 11:30 ?        00:00:00 lsphp
h209767  1090743  969310  0 11:49 ?        00:00:00 lsphp

```
