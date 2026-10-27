import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { problem, sessionHandler, merchantSession } from '../test/api';
import { PANEL_PRODUCT_PATH, panelProductHandler, productDetails } from '../test/panel';
import { renderApp } from '../test/render';
import { server } from '../test/server';

const ETAG = '"4"';

async function changeProduct(price: string, stock: string) {
  const user = userEvent.setup();
  const priceField = await screen.findByLabelText('Price (PLN)');
  await user.clear(priceField);
  await user.type(priceField, price);
  const stockField = screen.getByLabelText('Stock');
  await user.clear(stockField);
  await user.type(stockField, stock);
  await user.click(screen.getByRole('button', { name: 'Save' }));
}

describe('ProductEditPage', () => {
  it('saves the price and stock for the version that was loaded', async () => {
    let sent: { ifMatch: string | null; body: unknown } | null = null;
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      panelProductHandler(productDetails(7), ETAG),
      http.patch(PANEL_PRODUCT_PATH, async ({ request }) => {
        sent = { ifMatch: request.headers.get('If-Match'), body: await request.json() };

        return HttpResponse.json({ ...productDetails(7), stock: 12 }, { headers: { ETag: '"5"' } });
      }),
    );
    renderApp('/products/7/edit');

    expect(await screen.findByRole('heading', { name: 'Edit Mug' })).toBeInTheDocument();
    expect(screen.getByLabelText('Price (PLN)')).toHaveValue('19.99');
    expect(screen.getByText('3 units are reserved by open orders.')).toBeInTheDocument();

    await changeProduct('25', '12');

    expect(await screen.findByRole('status')).toHaveTextContent('Saved.');
    expect(sent).toEqual({
      ifMatch: ETAG,
      body: { price: { amount: '2500', currency: 'PLN' }, stock: 12 },
    });
  });

  it('explains that someone else changed the product', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      panelProductHandler(productDetails(7), ETAG),
      http.patch(PANEL_PRODUCT_PATH, () =>
        problem(412, 'The product changed since you loaded it; reload it and try again.'),
      ),
    );
    renderApp('/products/7/edit');

    await changeProduct('25', '12');

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'The product changed since you loaded it',
    );
  });

  it('checks the price before sending it', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      panelProductHandler(productDetails(7), ETAG),
    );
    renderApp('/products/7/edit');

    await changeProduct('19.999', '12');

    expect(screen.getByLabelText('Price (PLN)')).toHaveAccessibleDescription(
      'Enter an amount with at most 2 decimal places.',
    );
  });
});
