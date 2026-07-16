# Introduction

Token-authenticated JSON API for mobile and third-party clients.

<aside>
    <strong>Base URL</strong>: <code>http://127.0.0.1:8000</code>
</aside>

    The Inertia frontend is unaffected by this API — it keeps using session auth.

    **Identifiers are UUIDs.** Every table has a `bigint` `id` for foreign keys and joins,
    and a `uuid` as its public route key. `id` is hidden from JSON and never leaves the
    server, so passing one where a UUID belongs 404s before the query even runs.

    **Money is a string** — `"12.50"`, never `12.5`. The columns are `decimal(10,2)`, and a
    JSON float would drop the trailing zero and drift on sums. Parse it with a decimal type
    on the client. Percentages are not money and stay numeric (`percent: 106`).

    **Dates.** `spent_on` is a calendar day (`2026-07-16`), budgets are months (`2026-07`),
    timestamps are ISO 8601 UTC.

    **Rate limits.** 60 requests/minute per token. `login` and `register` allow 5 per minute
    per email+IP, plus 20 per minute per IP.

    <aside>Code examples for each endpoint are in the dark panel to the right; switch
    language with the tabs at the top.</aside>

