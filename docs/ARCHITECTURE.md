# Architecture

## Overview

Hexagonal (ports & adapters) layout keeps financial rules in `src/Domain` independent of Symfony and Doctrine.

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────────────┐
│  API Layer  │────▶│   Application    │────▶│  Domain (pure PHP)      │
│ Controllers │     │ TransferHandler  │     │ Account, Transfer, Money│
└─────────────┘     └────────┬─────────┘     └───────────┬─────────────┘
                             │                           │
                    ┌────────▼─────────┐         ┌─────────▼──────────┐
                    │ Infrastructure   │         │ Repository ports   │
                    │ Doctrine + Redis │◀────────│ (interfaces)       │
                    └──────────────────┘         └────────────────────┘
```

## Transfer flow

1. **Authenticate** — `X-Api-Key` (stateless firewall).
2. **Rate limit** — Redis-backed sliding window per API key.
3. **Idempotency** — If `Idempotency-Key` present, return cached transfer (Redis → DB).
4. **Transaction** — `wrapInTransaction`:
   - Lock accounts `FOR UPDATE` in **sorted UUID order** (deadlock prevention).
   - Validate balances, debit/credit in minor units.
   - Persist transfer as `completed`.
   - Invalidate Redis balance cache for both accounts.
5. **Respond** — `201 Created` or `200 OK` (replay).

## Money

Amounts stored as **integer minor units** (cents). `Money::fromMajor()` parses API decimals; avoids float rounding.

## Concurrency

| Mechanism | Purpose |
|-----------|---------|
| Pessimistic write locks | Serializable balance updates per account |
| Ordered lock acquisition | Prevents circular wait deadlocks |
| Optimistic `version` | Detects concurrent entity conflicts on flush |
| Idempotency (Redis + DB UNIQUE) | Safe retries under load |

## Fault tolerance

| Mechanism | Purpose |
|-----------|---------|
| Redis graceful degradation | Cache/idempotency failures fall back to MySQL |
| Rate limiter fail-open | Requests allowed when limiter backend is down |
| DB transactions + locks | Atomic balance updates survive partial failures |
| Idempotency (Redis + DB UNIQUE) | Safe retries under load |
| Health check (`/health`) | Reports degraded state when Redis or DB fails |

## Redis usage

| Key pattern | TTL | Role |
|-------------|-----|------|
| `idempotency:{key}` | 24h | Fast replay path |
| `balance:{accountId}` | 60s | Read-through cache for GET account |
| Rate limiter pool | 1m | Abuse protection |

Writes always go to MySQL; cache is invalidated on transfer.

## Security

- No session cookies; API keys from env (`API_KEYS` CSV).
- Validation via Symfony Validator on DTOs.
- Domain errors mapped to stable `error` codes (no stack traces in responses).
- Transfer channel logging (`var/log/transfer.log`).

## Testing strategy (TDD)

1. **Unit** — `Money`, `Account`, `TransferFundsHandler` (mocked infra).
2. **Integration** — Full HTTP kernel, real MySQL test DB, Redis; schema reset per test.
3. **Load** — k6 constant-arrival-rate scenario; thresholds on p95 latency and error rate.

## Schema

- `accounts` — balance in minor units, `version` for optimistic locking.
- `transfers` — immutable completed records; unique `reference` and `idempotency_key`.
