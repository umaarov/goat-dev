# Email

Six emails, all multilingual (en, ru, uz, es, pt_BR, tr, id, hi), dark-mode aware, with a plain-text part.

| Email | Class | View |
|---|---|---|
| Verify address | `Mail\EmailVerification` | `emails/verification` |
| Password reset | `Notifications\QueuedResetPassword` | `emails/auth/reset` |
| Welcome | `Mail\WelcomeMessage` | `emails/auth/welcome` |
| Registration expired | `Mail\RegistrationExpired` | `emails/registration-expired` |
| Unsubscribed | `Mail\UnsubscribedNotification` | `emails/notifications/unsubscribed` |
| New debates digest | `Mail\NewPostsNotification` | `emails/new_posts_notification` |

Text lives in `lang/<locale>/mail.php`; plain-text versions in `resources/views/emails/text/`. Shared pieces are the `x-mail.*` components (`layout`, `button`, `title`, `p`, `link-fallback`).
Mail goes to the user's saved locale (`User::preferredLocale()`).

## Preview

```
php artisan mail:samples                      # html + txt for all six into storage/app/mail-preview
php artisan mail:samples --locale=ru          # another language
php artisan mail:samples --to=me@example.com  # really send them (never in production)
node tests/e2e/mail-shots.mjs <dir>           # screenshots: desktop/mobile x light/dark
```

Locally, mail lands in Mailpit (the full-local compose stack). Its HTML check is a good compatibility gauge.

## Adding an email

1. Strings in all eight `lang/*/mail.php` files (`EmailsTest` checks keys and `:placeholders` match English).
2. HTML view built from `x-mail.layout`, plus a text view in `emails/text/`.
3. Mailable with `Content(view:, text:, with:)`, sent with `Mail::to($user)` so the locale follows the user.
4. Add it to `MailSamples` and `EmailsTest`.

## Unsubscribe

The digest carries `List-Unsubscribe` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` (RFC 8058) with a signed URL, so Gmail/Yahoo show their native button.

- `GET /email/{user}/unsubscribe` only shows a confirmation page (link scanners must not unsubscribe anyone).
- `POST` unsubscribes, queues one confirmation mail, writes an audit log line. `POST /email/{user}/resubscribe` undoes it.
- Routes are CSRF-exempt and protected by the URL signature (no token table, no expiry).
- Old links from earlier emails still work through the legacy token route.

## Gotchas

- `uz` in Carbon is Cyrillic; the app uses Latin, see `MailText::carbonLocale()` (`uz_Latn`).
- Queued notifications need `use Queueable` and `->locale()` set when queued.
- Logo is a white PNG on a dark header bar so it reads in light and dark clients; digest hero is a JPEG share card (WebP is not universal in mail clients).
- es, pt_BR, tr, id, hi were machine-assisted and need a native review.

## Deliverability checklist (DNS, not code)

- `MAIL_MAILER`, host and credentials set in production; `MAIL_FROM_ADDRESS` on the sending domain.
- SPF and DKIM published for that domain, DMARC at least `p=none` with a reporting address, later `quarantine`.
- Use a transactional provider (Postmark, SES, Resend, Mailgun); a plain SMTP box rarely stays out of spam.
- Set `MAIL_FOOTER_ADDRESS` to the real postal address.
- Test a real send at mail-tester.com / Gmail "Show original" (SPF/DKIM/DMARC = PASS).
