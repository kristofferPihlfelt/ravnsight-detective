# ChangeDetective

Records every plugin/theme/core change and option changes on an allowlist,
plus a daily environment snapshot with diffing — the "what changed before
it broke?" half of the product.

- Hooks: upgrader_process_complete, activated_plugin, deactivated_plugin,
  switch_theme, _core_updated_successfully, updated_option (allowlist only).
- The option allowlist is a hard boundary (DATA-POLICY): values are stored
  only for allowlisted options; everything else is invisible to us.
- Daily snapshot: WP/PHP versions, active plugins+versions, theme — stored
  locally, diffed against the previous snapshot.
