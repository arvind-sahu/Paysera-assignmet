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
2. **Validate request envelope** — header and content-type guard (`RequestValidationListener`) validates `X-Api-Key`/`Idempotency-Key` format and `application/json`.
3. **Rate limit** — Redis-backed sliding window per API key.
4. **Idempotency** — `Idempotency-Key` is mandatory for transfer creation; return cached transfer on replay (Redis → DB).
5. **Transaction** — `wrapInTransaction`:
   - Lock accounts `FOR UPDATE` in **sorted UUID order** (deadlock prevention).
   - Validate balances, debit/credit in minor units.
   - Persist transfer as `completed`.
   - Invalidate Redis balance cache for both accounts.
6. **Respond** — `201 Created` or `200 OK` (replay).

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
| Transfer rate limiter pool | 1m | 120 transfer transactions/minute per API key |

Writes always go to MySQL; cache is invalidated on transfer.

## Security

- No session cookies; API keys from env (`API_KEYS` CSV).
- Validation via Symfony Validator on DTOs.
- Header envelope validation via `RequestValidationListener` (strict idempotency/API key format + JSON content type).
- Domain errors mapped to stable `error` codes (no stack traces in responses).
- Transfer channel logging (`var/log/transfer.log`).

## Observability and logging

- Every transfer attempt is logged with context (`fromAccountId`, `toAccountId`, amount, currency, `idempotency_key`).
- Successful transfers are logged at `INFO`; failed attempts are logged at `ERROR`.
- In production, logs are emitted as structured JSON to stdout/stderr for shipping into centralized platforms (ELK, Datadog, etc.).

### Central logging implementation flow

1. **Application emits structured events**
   - `TransferFundsHandler` logs success at `INFO` (`Transfer completed`) and failures at `ERROR` (`Transfer failed`) with transfer context.
2. **Monolog enriches records**
   - `RequestContextProcessor` injects correlation metadata into every log (`request_id`, method, path, client IP, API key, idempotency key).
3. **Monolog routes logs by stream and severity**
   - General/service logs and transfer logs go to stdout in production.
   - Error-level logs additionally go to stderr for priority handling.
4. **Container runtime collects streams**
   - Docker runtime captures stdout/stderr from the `php` container.
5. **Log shipper forwards to central platform**
   - In production, Fluent Bit / Vector / Datadog Agent / Filebeat tails container logs and forwards to Elasticsearch/Datadog.
6. **Central index + dashboards**
   - Build dashboards and alerts on transfer error rate, idempotency replay volume, and p95 latency correlations.

### Suggested production architecture (logging plane)

```
Symfony App (Monolog JSON + request context)
            │
            ├── stdout (INFO/WARN) ──┐
            └── stderr (ERROR) ──────┤
                                     ▼
                         Container Runtime Logs
                                     ▼
                           Log Shipper / Agent
                                     ▼
                     ELK / Datadog / OpenSearch
                                     ▼
               Dashboards, Alerts, Retention, Search
```

## Scaling and recovery plan (10k requests/second)

This section describes how to evolve the current design to sustain very high traffic while remaining fault-tolerant and recoverable.

### 1) Ingress and API protection

- Place API behind load balancer + API gateway (WAF, TLS termination, request size limits).
- Keep strict per-key/per-tenant rate limits and burst controls at gateway and app level.
- Require `Idempotency-Key` for transfer create requests to make retries safe during failures.
- Add global circuit breakers and overload protection (shed non-critical traffic first).

### 2) Asynchronous transfer ingestion

- Introduce queue-first write path for high load:
  - API validates/authenticates/idempotency-checks and enqueues transfer command.
  - Return `202 Accepted` + tracking reference for async processing mode.
- Use RabbitMQ/Kafka with partitioning strategy (e.g., by source account hash) to reduce cross-worker contention.
- Run horizontally scalable consumers with controlled concurrency and back-pressure.

### 3) Data tier scaling

- Keep transfers as serializable financial writes on primary DB nodes.
- Shard accounts/transfers by tenant or account range to avoid single-writer bottlenecks.
- Use read replicas for query APIs (`GET transfers`, `GET account` projections).
- Maintain idempotency uniqueness at DB boundary (`UNIQUE(idempotency_key)`) in each shard.

### 4) Idempotency and consistency at scale

- Keep Redis as fast replay cache; DB unique key remains source-of-truth guard.
- Use outbox pattern for publishing events after DB commit (no dual-write loss).
- Build replay/reconciliation workers that can rebuild projections from transfer/ledger records.

### 5) Fault tolerance and disaster recovery

- **App node crash**: stateless app containers auto-restarted; in-flight requests safely retried with same idempotency key.
- **Redis crash**: system remains operational; fallback to DB idempotency + reduced cache performance.
- **DB primary crash**: automatic failover (managed MySQL/Aurora), reconnect + resume consumers.
- **Queue broker crash**: deploy clustered broker with persistent messages and dead-letter queues.
- **AZ/region outage**: multi-AZ active setup minimum; multi-region active/passive with defined RPO/RTO and failover drills.

### 6) Recovery runbook essentials

- Define SLOs and recovery targets (example: RTO 15 min, RPO < 1 min).
- Automate backups (full + binlog/PITR), and test restore regularly.
- Keep infrastructure as code for deterministic environment rebuild.
- Maintain runbooks for failover, queue drain, replay, and traffic cutover.
- Run chaos/failure drills (DB failover, Redis outage, broker partition, node eviction).

### 7) Monitoring and alerting for survival

- Metrics: ingress rate, queue lag, consumer throughput, DB lock waits/deadlocks, idempotency hit ratio, error budgets.
- Alerts: queue lag growth, retry exhaustion, replication lag, failover events, p95/p99 latency spikes.
- Tracing: end-to-end request/transfer correlation (`request_id`, idempotency key, transfer reference).

## Pattern decisions: SAGA and CQRS

### SAGA pattern — do we need it now?

- **Current state:** not implemented, because this service currently commits debit+credit in one MySQL transaction (single bounded context).
- **Need SAGA when:** transfer spans multiple independent services/datastores that cannot share one ACID transaction.
- **Future implementation path:** add orchestration + compensating actions (`debit_compensate`, `credit_compensate`) and durable state machine.

### CQRS pattern — do we need it now?

- **Current state:** partially aligned, but not full CQRS (reads and writes still share primary model/database).
- **Need CQRS when:** read traffic/reporting grows much faster than writes, or read shapes diverge heavily from write model.
- **Future implementation path:** keep write side authoritative; publish transfer events; maintain read projections in replica/search store for fast queries.

## Testing strategy (TDD)

1. **Unit** — `Money`, `Account`, `TransferFundsHandler` (mocked infra).
2. **Integration** — Full HTTP kernel, real MySQL test DB, Redis; schema reset per test.
3. **Load** — k6 constant-arrival-rate scenario; thresholds on p95 latency and error rate.

## Schema

- `accounts` — balance in minor units, `version` for optimistic locking.
- `transfers` — immutable completed records; unique `reference` and `idempotency_key`.
