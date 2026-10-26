import { setupServer } from 'msw/node';

/**
 * Fakes the backend at the network boundary. Tests register the responses they need with
 * server.use(); any request without a handler fails the test.
 */
export const server = setupServer();
