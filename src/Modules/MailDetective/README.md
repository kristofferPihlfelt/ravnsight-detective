# MailDetective

Records every failed wp_mail() with the actual transport error — the
silent failure class: WordPress reports success to the caller while the
host's mail() quietly discards, or an SMTP plugin's auth has expired.

- Hook: wp_mail_failed (fires for both PHP mail() and SMTP plugins that
  use PHPMailer properly).
- Recipient privacy: only the recipient DOMAIN is stored, never the
  address; subjects pass the Redactor.
- Attribution via backtrace: which plugin tried to send.
