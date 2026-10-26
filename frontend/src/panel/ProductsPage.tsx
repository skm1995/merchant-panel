import { apiClient } from '../api/client';
import type { components } from '../api/schema';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { DataTable, type Column } from '../ui/DataTable';
import { formatMoney } from '../ui/money';
import { Page } from '../ui/Page';
import { useCursorPages, type FetchPage } from './useCursorPages';

type Product = components['schemas']['Product'];

const COLUMNS: Column<Product>[] = [
  { header: 'SKU', cell: (product) => product.sku },
  { header: 'Name', cell: (product) => product.name },
  { header: 'Price', cell: (product) => formatMoney(product.price) },
  { header: 'Available', cell: (product) => String(product.available_quantity) },
];

const fetchProducts: FetchPage<Product, number> = async (cursor) => {
  const { data, error } = await apiClient.GET('/api/v1/products', {
    params: { query: cursor === null ? {} : { cursor } },
  });

  return data === undefined
    ? { error }
    : { page: { items: data.data, nextCursor: data.next_cursor } };
};

export function ProductsPage() {
  const { items, hasMore, showMore, error, busy } = useCursorPages(fetchProducts);

  return (
    <Page title="Products">
      {error !== null && <Alert tone="error">{error}</Alert>}
      <DataTable
        caption="Products"
        columns={COLUMNS}
        rows={items}
        rowKey={(product) => String(product.id)}
      />
      {hasMore && (
        <Button variant="secondary" busy={busy} onClick={showMore}>
          Show more
        </Button>
      )}
    </Page>
  );
}
