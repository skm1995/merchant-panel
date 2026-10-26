import { apiClient } from './client';

describe('apiClient', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('calls the API on the same origin with the session cookie and typed responses', async () => {
    const products = { data: [], next_cursor: null };
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify(products), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    vi.stubGlobal('fetch', fetchMock);

    const { data } = await apiClient.GET('/api/v1/products', { params: { query: { limit: 5 } } });

    const request = fetchMock.mock.calls[0]?.[0];
    expect(request).toBeInstanceOf(Request);
    expect((request as Request).url).toBe(`${window.location.origin}/api/v1/products?limit=5`);
    expect((request as Request).credentials).toBe('same-origin');
    expect(data).toEqual(products);
  });
});
