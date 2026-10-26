import { http, HttpResponse } from 'msw';
import type { components } from '../api/schema';

type OrderSummary = components['schemas']['OrderSummary'];
type OrderSummaryPage = components['schemas']['OrderSummaryPage'];
type Product = components['schemas']['Product'];
type ProductPage = components['schemas']['ProductPage'];

export const ORDERS_PATH = '/api/v1/panel/orders';
export const PRODUCTS_PATH = '/api/v1/products';

export function orderSummary(id: string, createdAt: string): OrderSummary {
  return { id, status: 'paid', total: { amount: '12345', currency: 'PLN' }, created_at: createdAt };
}

export function product(id: number, name: string): Product {
  return {
    id,
    sku: `SKU-${String(id)}`,
    name,
    price: { amount: '1999', currency: 'PLN' },
    available_quantity: 7,
  };
}

/**
 * Serves the given pages of orders keyed by the cursor that requests them; the first page
 * has no cursor.
 */
export function ordersHandler(
  firstPage: OrderSummary[],
  nextCursor: string | null = null,
  pages: Record<string, OrderSummaryPage> = {},
) {
  return http.get(ORDERS_PATH, ({ request }) => {
    const cursor = new URL(request.url).searchParams.get('cursor');

    if (cursor === null) {
      return HttpResponse.json<OrderSummaryPage>({ data: firstPage, next_cursor: nextCursor });
    }

    return HttpResponse.json<OrderSummaryPage>(pages[cursor] ?? { data: [], next_cursor: null });
  });
}

export function productsHandler(products: Product[]) {
  return http.get(PRODUCTS_PATH, () =>
    HttpResponse.json<ProductPage>({ data: products, next_cursor: null }),
  );
}
