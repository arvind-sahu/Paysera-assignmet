# API Reference

Base URL: `http://localhost:8080`

## Authentication

All `/api/*` routes require:

```
X-Api-Key: dev-api-key-001
```

## Versioning

| Version | Base path | Response shape |
|---------|-----------|----------------|
| v1 | `/api/v1/...` | Flat JSON objects |
| v2 | `/api/v2/...` | `{ "data": ..., "meta": { "apiVersion": "v2", ... } }` |

Future resources (users, products, orders) follow the same versioned layout:
`/api/v1/users`, `/api/v2/users`, etc.

---

## POST /api/v1/transfers

**Request**

```json
{
  "fromAccountId": "11111111-1111-4111-8111-111111111111",
  "toAccountId": "22222222-2222-4222-8222-222222222222",
  "amount": "10.50",
  "currency": "EUR"
}
```

**Headers (optional but recommended)**

```
Idempotency-Key: unique-client-key-min-8-chars
```

**Response `201 Created`**

```json
{
  "reference": "019262e0-7c8a-7000-8000-000000000001",
  "status": "completed",
  "fromAccountId": "...",
  "toAccountId": "...",
  "amount": "10.50",
  "currency": "EUR",
  "completedAt": "2024-05-21T12:00:00+00:00",
  "replayed": false
}
```

**Idempotent replay `200 OK`** — same body, `"replayed": true`.

---

## GET /api/v1/transfers

List transfers with pagination and optional filters.

| Query param | Description |
|-------------|-------------|
| `accountId` | Filter by sender or receiver UUID |
| `status` | `pending`, `completed`, or `failed` |
| `days` | Only transfers within the last N days |
| `page` | Page number (default `1`) |
| `limit` | Page size 1–100 (default `20`) |

**Response `200 OK`**

```json
{
  "items": [
    {
      "reference": "...",
      "status": "completed",
      "fromAccountId": "...",
      "toAccountId": "...",
      "amount": "10.50",
      "currency": "EUR",
      "createdAt": "2024-05-21T12:00:00+00:00",
      "completedAt": "2024-05-21T12:00:00+00:00",
      "replayed": false
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 1,
    "totalPages": 1,
    "hasNextPage": false
  }
}
```

---

## GET /api/v1/transfers/recent

Same as list, but defaults to the **last 30 days**. Override with `?days=7`.

Response includes `"periodDays": 30`.

---

## GET /api/v1/transfers/{reference}

Returns transfer details by UUID v7 reference.

---

## GET /api/v1/accounts/{id}

```json
{
  "id": "...",
  "accountNumber": "LT611111111111111111",
  "balance": "100000.00",
  "currency": "EUR",
  "active": true,
  "cached": false
}
```

---

## GET /api/v1/accounts/{id}/transfers

Paginated transfers where the account is sender or receiver. Supports the same query params as `GET /api/v1/transfers`.

---

## API v2

All v2 endpoints mirror v1 behavior but wrap responses:

```json
{
  "data": { ... },
  "meta": {
    "apiVersion": "v2",
    "timestamp": "2024-05-21T12:00:00+00:00",
    "pagination": { ... }
  }
}
```

| Method | Path | Notes |
|--------|------|-------|
| `POST` | `/api/v2/transfers` | Optional `description` field in request body |
| `GET` | `/api/v2/transfers` | Envelope + pagination in `meta` |
| `GET` | `/api/v2/transfers/recent` | Includes `periodDays` in `meta` |
| `GET` | `/api/v2/transfers/{reference}` | Single transfer in `data` |

---

## Error codes

| HTTP | error | When |
|------|-------|------|
| 401 | authentication_failed | Missing/invalid API key |
| 404 | ACCOUNT_NOT_FOUND | Unknown account UUID |
| 404 | TRANSFER_NOT_FOUND | Unknown transfer reference |
| 422 | INSUFFICIENT_FUNDS | Debit would overdraw |
| 422 | SAME_ACCOUNT | from === to |
| 422 | INVALID_STATUS | Invalid status filter |
| 422 | validation_failed | Invalid payload |
| 429 | rate_limit_exceeded | >120 requests/min per key |

---

## GET /health

No authentication. Returns database and Redis check status. Returns `503` when degraded.
