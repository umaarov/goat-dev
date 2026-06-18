# GOAT Mobile API — v1 Reference

Base URL: `https://<your-host>/api/v1`

A versioned JSON API that mirrors the GOAT web platform (this-or-that posts,
votes, threaded comments, likes, shares, notifications, ratings, profiles and
social auth) for native mobile clients.

---

## Conventions

### Response envelope

Every successful response is wrapped:

```json
{ "success": true, "data": { ... } }
```

Paginated responses add `meta`:

```json
{
  "success": true,
  "data": [ ... ],
  "meta": { "current_page": 1, "last_page": 4, "per_page": 15, "total": 52, "has_more_pages": true }
}
```

### Errors

```json
{ "success": false, "error_code": "validation_failed", "message": "…", "errors": { "field": ["…"] } }
```

| Status | `error_code` examples |
|--------|------------------------|
| 401 | `authentication_required`, `invalid_credentials`, `invalid_refresh_token`, `email_not_verified`, `social_verification_failed` |
| 403 | `access_forbidden` |
| 404 | `not_found` |
| 409 | `already_voted`, `post_already_voted`, `social_link_conflict`, `last_auth_method` |
| 422 | `validation_failed`, `content_rejected` |
| 429 | `rate_limit_exceeded` |
| 500 | `server_error`, `internal_server_error` |

### Authentication

Send the access token on every protected request:

```
Authorization: Bearer <access_token>
Accept: application/json
```

- **Access token**: short-lived Sanctum bearer token (default 60 min, configurable via `API_ACCESS_TOKEN_MINUTES`).
- **Refresh token**: long-lived (default 90 days). Exchange it at `POST /auth/refresh` to get a new pair. Refresh tokens rotate on every use; reusing a rotated token outside a 60s grace window revokes all of the user's sessions (theft protection).

Uploads use `multipart/form-data`; everything else accepts JSON.

---

## Auth

### POST /auth/register
Public. `multipart` or JSON.

| field | rules |
|-------|-------|
| first_name | required, ≤255 |
| last_name | optional |
| username | required, 5–24, `^[a-zA-Z][a-zA-Z0-9_-]*$`, unique |
| email | required, email, unique |
| password | required, ≥8, `password_confirmation` must match |
| profile_picture | optional image ≤2MB |
| terms_accepted | must be accepted (`true`/`1`) |

Returns `201` with `verification_required: true`. A verification email is sent; the user must verify before logging in.

### POST /auth/email/verify
Public. `{ "id": 123, "token": "<token-from-email>" }`. On success returns the full token pair + user (auto sign-in).

### POST /auth/email/resend
Auth. Resends the verification email.

### POST /auth/login
Public. `{ "login_identifier": "email-or-username", "password": "…", "device_name": "iPhone 15" }`

```json
{ "success": true, "data": {
  "access_token": "…", "refresh_token": "…", "token_type": "Bearer", "expires_in": 3600,
  "user": { "id": 1, "username": "…", "email": "…", … }
}}
```
Soft-deleted accounts within 30 days are reactivated automatically on login.

### POST /auth/refresh
Public. `{ "refresh_token": "…" }` → new `{ access_token, refresh_token, token_type, expires_in }`.

### POST /auth/logout
Auth. `{ "refresh_token": "…" }` (optional). Revokes the current access token + the given refresh token.

### POST /auth/logout-all
Auth. Revokes every access and refresh token for the user.

### GET /auth/me
Auth. Returns the current user (includes private fields: email, preferences, linked providers).

### POST /auth/password/forgot
Public. `{ "email": "…" }`. Always returns success (no account enumeration).

### POST /auth/password/reset
Public. `{ "token", "email", "password", "password_confirmation" }`.

### POST /auth/social/{provider}  — `google | x | telegram | github`
Public. Native social sign-in. Body:
- Google: `{ "token": "<id_token>" }`
- X / GitHub: `{ "token": "<oauth_access_token>" }`
- Telegram: `{ "telegram": { id, hash, auth_date, first_name, … } }` (Login Widget payload)

The server verifies the credential, finds/creates/links the account and returns the token pair + user.

### GET /auth/sessions
Auth. Lists active refresh-token sessions (device, ip, created/expires).

### DELETE /auth/sessions/{id}
Auth. Revokes one session.

---

## Posts

### GET /posts
Public. Query: `filter=latest|trending`, `page`. Paginated `PostResource`.

### GET /posts/search?q=
Public. Full-text search (GOAT search engine). Paginated.

### GET /posts/{post}
Public. Single post; includes `comments_count`, `shares_relation_count`, and `user_vote` for the authenticated caller.

### POST /posts
Auth. `multipart/form-data`. Runs AI text + image moderation before saving.

| field | rules |
|-------|-------|
| question | required ≤255 |
| option_one_title / option_two_title | required ≤40 |
| option_one_image / option_two_image | required image (jpeg/png/jpg/webp) ≤2MB |

Returns `201` `PostResource`. Rejected content → `422 content_rejected` with the offending field.

### PUT /posts/{post}
Auth (owner). Only allowed while `total_votes == 0`. Same fields as create but images are optional; supports `remove_option_one_image` / `remove_option_two_image` booleans.

### DELETE /posts/{post}
Auth (owner). Deletes media + votes.

### POST /posts/{post}/vote
Auth. `{ "option": "option_one" | "option_two" }`. Returns updated counts + percentages + `user_vote`. Voting twice → `409 already_voted`.

### POST /posts/{post}/share
Auth. `{ "platform": "twitter|facebook|whatsapp|telegram|email|link_copy" }`. Returns `{ shares_count }`.

### GET /users/{username}/posts
Public. Paginated posts by a user.

### GET /users/{username}/voted-posts
Public/auth. Respects the owner's `show_voted_posts_publicly` privacy flag (`403` if private and not the owner).

---

## Comments

### GET /posts/{post}/comments
Public. Top-level comments ranked by `score`, each with up to 3 preview replies, `likes_count`, `replies_count`, and `is_liked_by_current_user`. Query: `per_page`, `page`.

### GET /comments/{comment}/replies
Public. Paginated full reply thread. Query: `exclude_ids[]`, `page`.

### POST /posts/{post}/comments
Auth. `{ "content": "…", "parent_id": 12 }` (parent optional). URL + text AI moderation; rejected → `422 content_rejected`. Triggers reply/mention notifications.

### PUT /comments/{comment}
Auth (owner). `{ "content": "…" }`.

### DELETE /comments/{comment}
Auth (comment owner OR post owner).

### POST /comments/{comment}/like
Auth. Toggles like. Returns `{ is_liked, likes_count }`.

---

## Profile / "me"

### GET /users/{username}
Public. Profile + `stats` (posts_count, total_votes_received) + `badges`.

### GET /users/check-username?username=
Public. `{ available, message }` (format, uniqueness and moderation checks).

### PUT /me
Auth. `multipart` or JSON. All fields optional (partial update). Names/username/picture/links are AI-moderated.
Fields: `first_name`, `last_name`, `username`, `show_voted_posts_publicly`, `receives_notifications`,
`ai_insight_preference` (`expanded|less|hidden`), `locale`, `external_links[]` (≤3 urls),
`profile_picture` (image) / `remove_profile_picture`,
`header_background_upload` (image) / `header_background_template` (`template_1.jpg`…`template_12.jpg`) / `remove_header_background`.

### POST /me/change-password
Auth. `{ current_password, new_password, new_password_confirmation }`.

### POST /me/password
Auth. Set a first password (social-only accounts). `{ password, password_confirmation }`.

### DELETE /me/password
Auth. Remove password (blocked if it's the last auth method).

### POST /me/profile-picture/generate
Auth. `{ "prompt": "…" }` (10–350 chars). AI avatar generation. Limits: 2/day, 5/month. Returns new URL + remaining quota.

### POST /me/social/{provider}
Auth. Link a verified provider (same body as `/auth/social/{provider}`).

### DELETE /me/social/{provider}
Auth. Unlink (blocked if it's the last auth method).

### DELETE /me
Auth. Deactivate (soft-delete + anonymise). Revokes all tokens. Re-login within 30 days reactivates.

### GET /me/export
Auth. Full personal-data export (identity, posts, votes, comments, linked accounts).

### POST /me/heartbeat
Auth. Updates the "online" presence timestamp.

---

## Notifications

### GET /notifications
Auth. Paginated. Does **not** auto-mark read.

### GET /notifications/unread-count
Auth. `{ count }`.

### PATCH /notifications/{id}/read
Auth. Mark one read.

### POST /notifications/read-all
Auth. Mark all read.

---

## Devices & push notifications

The app delivers push via **Firebase Cloud Messaging (FCM)**. Register the device's FCM token after login; the backend then sends a push alongside every in-app (database) notification — replies, mentions and comment likes carry a `data.type` of `comment_reply`, `mention`, or `comment_liked` plus the relevant ids for deep-linking.

Server config (`.env`): `FCM_PROJECT_ID`, `FCM_CREDENTIALS` (path to a Firebase service-account JSON). When unset, push is silently skipped (DB notifications still work).

### GET /me/devices
Auth. List the caller's registered devices.

### POST /me/devices
Auth. `{ "token": "<fcm_token>", "platform": "ios|android|web" }`. Registers or refreshes the token (idempotent; a token already bound elsewhere is reassigned to the caller). Returns `201`.

### DELETE /me/devices
Auth. `{ "token": "<fcm_token>" }`. Unregister a device (call on logout). Unregistered/expired tokens are also pruned automatically when FCM reports them stale.

---

## Ratings & meta

### GET /ratings
Public. Leaderboards: `top_by_post_votes`, `top_by_post_count`, `top_by_comment_count`, `top_by_comment_likes` (each an array of `{ id, username, first_name, last_name, profile_picture, score }`).

### GET /meta/config
Public. Client bootstrap config: locales, limits, share platforms, vote options, feed filters.

---

## Rate limits (per minute, per IP/user unless noted)

| Endpoint | Limit |
|----------|-------|
| register / login / password.* / social | 5–10 |
| refresh | 20 |
| email/resend | 2 |
| posts (create) | 10 |
| vote / share / comment | 30 |
| comment like | 60 |
| me/export | 3 / hour |

---

## Resource shapes (abridged)

**PostResource**
```json
{
  "id": 1, "question": "…",
  "option_one": { "title": "…", "image": "https://…", "image_lqip": "data:…", "votes": 12, "percentage": 60.0 },
  "option_two": { "title": "…", "image": "https://…", "image_lqip": "data:…", "votes": 8,  "percentage": 40.0 },
  "total_votes": 20, "view_count": 0, "shares_count": 3, "comments_count": 5,
  "user_vote": "option_one",
  "user": { "id": 7, "username": "jane", "first_name": "Jane", "profile_picture": "https://…" },
  "created_at": "2026-06-17T10:00:00+00:00"
}
```

**CommentResource**
```json
{
  "id": 9, "content": "…", "post_id": 1, "parent_id": null, "root_comment_id": null,
  "likes_count": 4, "replies_count": 2, "is_liked_by_current_user": true,
  "user": { … }, "parent": null, "replies": [ … ],
  "created_at": "…"
}
```

All image fields are absolute URLs (or `data:` URIs for LQIP placeholders).
