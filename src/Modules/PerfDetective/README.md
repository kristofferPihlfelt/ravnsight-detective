# PerfDetective

Answers "WHY is the site slow?" locally: slow requests with their query
count and memory, near-limit memory use, and (when SAVEQUERIES is on)
the specific slow query shapes.

- Everything runs on shutdown — zero cost during the request itself.
- Thresholds are filterable: ravndet_slow_request_ms (default 3000),
  ravndet_memory_warn_ratio (default 0.85).
- Query capture requires SAVEQUERIES (WordPress's own mechanism); without
  it we still record duration + query COUNT per slow request.
