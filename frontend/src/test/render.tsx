import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { AppRoutes } from '../AppRoutes';
import { SessionProvider } from '../session/SessionProvider';

/**
 * Renders the whole application at a path, as the browser would open it.
 */
export function renderApp(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <SessionProvider>
        <AppRoutes />
      </SessionProvider>
    </MemoryRouter>,
  );
}
