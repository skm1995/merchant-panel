import { Navigate, Route, Routes } from 'react-router';
import { ConfirmEmailPage } from './auth/ConfirmEmailPage';
import { LoginPage } from './auth/LoginPage';
import { RegisterPage } from './auth/RegisterPage';
import { OrdersPage } from './panel/OrdersPage';
import { PanelLayout } from './panel/PanelLayout';
import { ProductsPage } from './panel/ProductsPage';
import { ReportsPage } from './panel/ReportsPage';
import { RequireSignedIn } from './session/RequireSignedIn';

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/confirm" element={<ConfirmEmailPage />} />
      <Route element={<RequireSignedIn />}>
        <Route element={<PanelLayout />}>
          <Route index element={<Navigate to="/orders" replace />} />
          <Route path="/orders" element={<OrdersPage />} />
          <Route path="/products" element={<ProductsPage />} />
          <Route path="/reports" element={<ReportsPage />} />
        </Route>
      </Route>
    </Routes>
  );
}
