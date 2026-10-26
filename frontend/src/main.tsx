import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router';
import { AppRoutes } from './AppRoutes';
import { SessionProvider } from './session/SessionProvider';
import './ui/tokens.css';

const ROOT_ELEMENT_ID = 'root';

const rootElement = document.getElementById(ROOT_ELEMENT_ID);

if (rootElement === null) {
  throw new Error(`Missing #${ROOT_ELEMENT_ID} element.`);
}

createRoot(rootElement).render(
  <StrictMode>
    <BrowserRouter>
      <SessionProvider>
        <AppRoutes />
      </SessionProvider>
    </BrowserRouter>
  </StrictMode>,
);
