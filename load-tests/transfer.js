import http from 'k6/http';
import { check, sleep } from 'k6';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';
const API_KEY = __ENV.API_KEY || 'dev-api-key-001';

// Seeded account UUIDs (see app:seed-demo-accounts)
const ALICE = '11111111-1111-4111-8111-111111111111';
const BOB = '22222222-2222-4222-8222-222222222222';
const CHARLIE = '33333333-3333-4333-8333-333333333333';

export const options = {
  scenarios: {
    steady_load: {
      executor: 'constant-arrival-rate',
      rate: 50,
      timeUnit: '1s',
      duration: '30s',
      preAllocatedVUs: 20,
      maxVUs: 80,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<500'],
  },
};

const accounts = [ALICE, BOB, CHARLIE];

export default function () {
  const from = accounts[__VU % accounts.length];
  const to = accounts[(__VU + 1) % accounts.length];
  if (from === to) {
    return;
  }

  const payload = JSON.stringify({
    fromAccountId: from,
    toAccountId: to,
    amount: '0.01',
    currency: 'EUR',
  });

  const idempotencyKey = `k6-${__VU}-${__ITER}-${uuidv4()}`;

  const res = http.post(`${BASE_URL}/api/v1/transfers`, payload, {
    headers: {
      'Content-Type': 'application/json',
      'X-Api-Key': API_KEY,
      'Idempotency-Key': idempotencyKey,
    },
  });

  check(res, {
    'status is 201 or 200': (r) => r.status === 201 || r.status === 200,
    'has reference': (r) => {
      try {
        return JSON.parse(r.body).reference !== undefined;
      } catch {
        return false;
      }
    },
  });

  sleep(0.01);
}
