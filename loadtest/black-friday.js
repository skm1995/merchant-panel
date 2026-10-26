// Black Friday load test: shoppers browse the catalog while others check out, pay, and
// receive provider webhooks (each delivered twice) against the test stack behind nginx.
// Run it with bin/loadtest, which seeds the products and checks stock invariants afterwards.
import { check } from 'k6';
import crypto from 'k6/crypto';
import http from 'k6/http';

const BASE_URL = requiredEnvironment('BASE_URL');
const WEBHOOK_SECRET = requiredEnvironment('FAKEPAY_WEBHOOK_SECRET');
const PRODUCT_IDS = requiredEnvironment('PRODUCT_IDS').split(',').map(Number);
const JSON_HEADERS = { 'Content-Type': 'application/json' };

http.setResponseCallback(http.expectedStatuses(200, 201, 409));

export const options = {
  scenarios: {
    browse: {
      executor: 'constant-arrival-rate',
      exec: 'browse',
      rate: 40,
      timeUnit: '1s',
      duration: '45s',
      preAllocatedVUs: 20,
      maxVUs: 60,
    },
    checkout: {
      executor: 'ramping-arrival-rate',
      exec: 'checkout',
      startRate: 2,
      timeUnit: '1s',
      stages: [
        { target: 15, duration: '20s' },
        { target: 25, duration: '15s' },
        { target: 0, duration: '10s' },
      ],
      preAllocatedVUs: 30,
      maxVUs: 100,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800'],
    'checks{check:order placed or sold out}': ['rate>0.99'],
  },
};

function requiredEnvironment(name) {
  const value = __ENV[name];

  if (value === undefined || value.length === 0) {
    throw new Error(`Required environment variable "${name}" is not set.`);
  }

  return value;
}

function randomProductId() {
  return PRODUCT_IDS[Math.floor(Math.random() * PRODUCT_IDS.length)];
}

export function browse() {
  const page = http.get(`${BASE_URL}/api/v1/products?limit=20`, { tags: { name: 'list products' } });
  check(page, { 'catalog page loads': (response) => response.status === 200 });

  const product = http.get(`${BASE_URL}/api/v1/products/${randomProductId()}`, { tags: { name: 'show product' } });
  check(product, { 'product loads': (response) => response.status === 200 });
}

export function checkout() {
  const cart = http.post(`${BASE_URL}/api/v1/carts`, null, { tags: { name: 'create cart' } }).json();
  http.put(
    `${BASE_URL}/api/v1/carts/${cart.id}/items/${randomProductId()}`,
    JSON.stringify({ quantity: 1 + Math.floor(Math.random() * 3) }),
    { headers: JSON_HEADERS, tags: { name: 'add to cart' } },
  );

  const order = http.post(`${BASE_URL}/api/v1/carts/${cart.id}/checkout`, null, {
    headers: { 'Idempotency-Key': `${cart.id}-checkout` },
    tags: { name: 'checkout' },
  });
  check(order, { 'order placed or sold out': (response) => response.status === 201 || response.status === 409 });

  if (order.status !== 201 || Math.random() < 0.3) {
    return;
  }

  pay(order.json());
}

function pay(order) {
  const payment = http.post(`${BASE_URL}/api/v1/orders/${order.id}/payments`, JSON.stringify({ provider: 'fakepay' }), {
    headers: { ...JSON_HEADERS, 'Idempotency-Key': `${order.id}-payment` },
    tags: { name: 'start payment' },
  });

  if (!check(payment, { 'payment started': (response) => response.status === 201 })) {
    return;
  }

  const started = payment.json();
  const body = JSON.stringify({
    id: `evt-${started.id}`,
    payment_id: started.checkout_url.split('/').pop(),
    status: 'succeeded',
    amount: Number(started.amount.amount),
    currency: started.amount.currency,
  });
  const headers = { ...JSON_HEADERS, 'X-Fakepay-Signature': crypto.hmac('sha256', WEBHOOK_SECRET, body, 'hex') };

  for (const delivery of ['first delivery', 'redelivery']) {
    const webhook = http.post(`${BASE_URL}/api/v1/payments/webhooks/fakepay`, body, { headers, tags: { name: `webhook ${delivery}` } });
    check(webhook, { 'webhook accepted': (response) => response.status === 200 });
  }
}
