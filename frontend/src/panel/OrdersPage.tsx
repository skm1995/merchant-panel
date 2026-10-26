import { apiClient } from '../api/client';
import type { components } from '../api/schema';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { DataTable, type Column } from '../ui/DataTable';
import { formatMoney } from '../ui/money';
import { Page } from '../ui/Page';
import { useCursorPages, type FetchPage } from './useCursorPages';

type OrderSummary = components['schemas']['OrderSummary'];

const COLUMNS: Column<OrderSummary>[] = [
  { header: 'Placed', cell: (order) => new Date(order.created_at).toLocaleString() },
  { header: 'Order', cell: (order) => order.id },
  { header: 'Status', cell: (order) => order.status },
  { header: 'Total', cell: (order) => formatMoney(order.total) },
];

const fetchOrders: FetchPage<OrderSummary, string> = async (cursor) => {
  const { data, error } = await apiClient.GET('/api/v1/panel/orders', {
    params: { query: cursor === null ? {} : { cursor } },
  });

  return data === undefined
    ? { error }
    : { page: { items: data.data, nextCursor: data.next_cursor } };
};

export function OrdersPage() {
  const { items, hasMore, showMore, error, busy } = useCursorPages(fetchOrders);

  return (
    <Page title="Orders">
      {error !== null && <Alert tone="error">{error}</Alert>}
      <DataTable caption="Orders" columns={COLUMNS} rows={items} rowKey={(order) => order.id} />
      {hasMore && (
        <Button variant="secondary" busy={busy} onClick={showMore}>
          Show more
        </Button>
      )}
    </Page>
  );
}
