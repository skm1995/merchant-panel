import { useEffect, useState, type SubmitEvent } from 'react';
import { Link, useParams } from 'react-router';
import { apiClient } from '../api/client';
import { problemMessage } from '../api/problem';
import type { components } from '../api/schema';
import { Alert } from '../ui/Alert';
import { Button } from '../ui/Button';
import { decimalPlaces, toDecimalInput, toMinorUnits } from '../ui/money';
import { Page } from '../ui/Page';
import { TextField } from '../ui/TextField';

type ProductDetails = components['schemas']['ProductDetails'];

const ETAG_HEADER = 'ETag';
const WHOLE_NUMBER = /^\d+$/;

interface Loaded {
  product: ProductDetails;
  etag: string;
}

/**
 * Replaces the retired legacy stock page: changes a product's price and stock for the
 * version the merchant loaded, so concurrent changes are never overwritten silently.
 */
export function ProductEditPage() {
  const productId = Number(useParams().id);
  const [loaded, setLoaded] = useState<Loaded | null>(null);
  const [price, setPrice] = useState('');
  const [stock, setStock] = useState('');
  const [priceError, setPriceError] = useState<string | undefined>(undefined);
  const [stockError, setStockError] = useState<string | undefined>(undefined);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let mounted = true;

    void apiClient
      .GET('/api/v1/panel/products/{id}', { params: { path: { id: productId } } })
      .then(({ data, error: failure, response }) => {
        if (!mounted) {
          return;
        }

        if (data === undefined) {
          setError(problemMessage(failure));

          return;
        }

        setLoaded({ product: data, etag: response.headers.get(ETAG_HEADER) ?? '' });
        setPrice(toDecimalInput(data.price));
        setStock(String(data.stock));
      });

    return () => {
      mounted = false;
    };
  }, [productId]);

  if (loaded === null) {
    return (
      <Page title="Edit product">
        {error === null ? <p aria-busy="true">Loading…</p> : <Alert tone="error">{error}</Alert>}
      </Page>
    );
  }

  const { product, etag } = loaded;
  const currency = product.price.currency;

  async function save(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault();
    const amount = toMinorUnits(price, currency);
    const validStock = WHOLE_NUMBER.test(stock);
    setPriceError(
      amount === null
        ? `Enter an amount with at most ${String(decimalPlaces(currency))} decimal places.`
        : undefined,
    );
    setStockError(validStock ? undefined : 'Enter a whole number of units.');

    if (amount === null || !validStock) {
      return;
    }

    setBusy(true);
    const {
      data,
      error: failure,
      response,
    } = await apiClient.PATCH('/api/v1/panel/products/{id}', {
      params: { path: { id: product.id }, header: { 'If-Match': etag } },
      body: { price: { amount, currency }, stock: Number(stock) },
    });
    setBusy(false);

    if (data === undefined) {
      setSaved(false);
      setError(problemMessage(failure));

      return;
    }

    setError(null);
    setSaved(true);
    setLoaded({ product: data, etag: response.headers.get(ETAG_HEADER) ?? etag });
  }

  return (
    <Page title={`Edit ${product.name}`}>
      {saved && <Alert tone="success">Saved.</Alert>}
      {error !== null && <Alert tone="error">{error}</Alert>}
      <p>SKU {product.sku}</p>
      <p>{product.reserved} units are reserved by open orders.</p>
      <form onSubmit={(event) => void save(event)}>
        <TextField
          label={`Price (${currency})`}
          value={price}
          onChange={setPrice}
          error={priceError}
        />
        <TextField label="Stock" value={stock} onChange={setStock} error={stockError} />
        <Button type="submit" busy={busy}>
          Save
        </Button>
      </form>
      <p>
        <Link to="/products">Back to products</Link>
      </p>
    </Page>
  );
}
