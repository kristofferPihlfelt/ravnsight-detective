# JsDetective

Captures JavaScript errors and unhandled promise rejections from the
site's OWN front end and records them as error.js_error signals.

- The reporter posts to the site's own REST API — same origin, never an
  external request (the free no-phone-home guarantee holds).
- Abuse hardening on the endpoint: same-site referer check, strict field
  validation, small payload cap, per-IP throttle.
- The reporter script is ~1 KB, deferred, and does nothing until an error
  actually happens.
