import { lazy, Suspense, type ReactNode } from 'react';
import { Navigate, Route, Routes } from 'react-router';
import { ConfirmEmailPage } from './auth/ConfirmEmailPage';
import { LoginPage } from './auth/LoginPage';
import { RegisterPage } from './auth/RegisterPage';
import { OrdersPage } from './panel/OrdersPage';
import { PanelLayout } from './panel/PanelLayout';
import { ProductEditPage } from './panel/ProductEditPage';
import { ProductsPage } from './panel/ProductsPage';
import { RequireMerchant } from './session/RequireMerchant';

// The report pages load the charting library, so they are fetched only when opened.
const ReportsPage = lazy(() =>
  import('./panel/ReportsPage').then((module) => ({ default: module.ReportsPage })),
);
const BlackFridayPage = lazy(() =>
  import('./panel/BlackFridayPage').then((module) => ({ default: module.BlackFridayPage })),
);
const AnalyticsPage = lazy(() =>
  import('./panel/AnalyticsPage').then((module) => ({ default: module.AnalyticsPage })),
);

function Loading({ children }: { children: ReactNode }) {
  return <Suspense fallback={<p>Loading…</p>}>{children}</Suspense>;
}

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/confirm" element={<ConfirmEmailPage />} />
      <Route element={<RequireMerchant />}>
        <Route element={<PanelLayout />}>
          <Route index element={<Navigate to="/orders" replace />} />
          <Route path="/orders" element={<OrdersPage />} />
          <Route path="/products" element={<ProductsPage />} />
          <Route path="/products/:id/edit" element={<ProductEditPage />} />
          <Route
            path="/reports"
            element={
              <Loading>
                <ReportsPage />
              </Loading>
            }
          />
          <Route
            path="/black-friday"
            element={
              <Loading>
                <BlackFridayPage />
              </Loading>
            }
          />
          <Route
            path="/analytics"
            element={
              <Loading>
                <AnalyticsPage />
              </Loading>
            }
          />
        </Route>
      </Route>
    </Routes>
  );
}
