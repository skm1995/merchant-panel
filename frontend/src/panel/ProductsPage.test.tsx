import { screen, within } from '@testing-library/react';
import { sessionHandler, merchantSession } from '../test/api';
import { product, productsHandler } from '../test/panel';
import { renderApp } from '../test/render';
import { server } from '../test/server';

describe('ProductsPage', () => {
  it('lists products with price and availability', async () => {
    server.use(
      sessionHandler(merchantSession('merchant@example.com')),
      productsHandler([product(7, 'Mug')]),
    );
    renderApp('/products');

    const table = await screen.findByRole('table', { name: 'Products' });
    expect(await within(table).findByRole('cell', { name: 'Mug' })).toBeInTheDocument();
    expect(within(table).getByRole('cell', { name: 'SKU-7' })).toBeInTheDocument();
    expect(within(table).getByRole('cell', { name: /^PLN\s19\.99$/ })).toBeInTheDocument();
    expect(within(table).getByRole('cell', { name: '7' })).toBeInTheDocument();
    expect(within(table).getByRole('link', { name: 'Edit Mug' })).toHaveAttribute(
      'href',
      '/products/7/edit',
    );
  });
});
