import { http, HttpResponse } from 'msw';
import { server } from '../test/server';
import { apiClient, rememberCsrfToken } from './client';

const TOKEN = 'remembered-token';

describe('apiClient', () => {
  it('calls the API on the same origin and returns typed responses', async () => {
    const products = { data: [], next_cursor: null };
    let requestedUrl = '';
    server.use(
      http.get('/api/v1/products', ({ request }) => {
        requestedUrl = request.url;

        return HttpResponse.json(products);
      }),
    );

    const { data } = await apiClient.GET('/api/v1/products', { params: { query: { limit: 5 } } });

    expect(requestedUrl).toBe(`${window.location.origin}/api/v1/products?limit=5`);
    expect(data).toEqual(products);
  });

  it('sends the remembered CSRF token with state changes only', async () => {
    const headers: (string | null)[] = [];
    server.use(
      http.get('/api/v1/session', ({ request }) => {
        headers.push(request.headers.get('X-CSRF-Token'));

        return HttpResponse.json({ authenticated: false, email: null, csrf_token: TOKEN });
      }),
      http.delete('/api/v1/session', ({ request }) => {
        headers.push(request.headers.get('X-CSRF-Token'));

        return new HttpResponse(null, { status: 204 });
      }),
    );
    rememberCsrfToken(TOKEN);

    await apiClient.GET('/api/v1/session');
    await apiClient.DELETE('/api/v1/session');

    expect(headers).toEqual([null, TOKEN]);
  });
});
