import { Navigate, Route, Routes } from 'react-router';
import { ConfirmEmailPage } from './auth/ConfirmEmailPage';
import { LoginPage } from './auth/LoginPage';
import { RegisterPage } from './auth/RegisterPage';
import { OrdersPage } from './panel/OrdersPage';
import { PanelLayout } from './panel/PanelLayout';
import { ProductEditPage } from './panel/ProductEditPage';
import { ProductsPage } from './panel/ProductsPage';
import { ReportsPage } from './panel/ReportsPage';
import { RequireMerchant } from './session/RequireMerchant';

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
          <Route path="/reports" element={<ReportsPage />} />
        </Route>
      </Route>
    </Routes>
  );
}
