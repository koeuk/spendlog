# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

Get a token from `POST /api/v1/login`, then send it as `Authorization: Bearer {token}`.

**Two gates, and both must pass.** Token *abilities* limit what the client may attempt;
*policies* limit what the user may do. A mobile token cannot write categories even when
its owner is an admin.

New tokens get every ability **except** `categories:write` — category management is an
admin desk job, and a lost phone should not rewrite the taxonomy every user's expenses
hang off. A client may request a narrower token via `abilities[]`; anything it asks for
is intersected with what the user may grant, so asking for more never widens it.
