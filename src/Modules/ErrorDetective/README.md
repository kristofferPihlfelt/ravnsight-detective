# ErrorDetective

Catches PHP errors and fatals, groups them by fingerprint, attributes them
to the plugin/theme/core that caused them, and detects spikes.

- Chained `set_error_handler` — always delegates to the previous handler.
- `register_shutdown_function` catches fatals/OOM the handler cannot see.
- Overhead: the handler does one redaction + one upsert per NEW fingerprint;
  repeats are a single UPDATE. Nothing runs on requests without errors.
- Never attributes to itself; never rethrows; never changes error output.
