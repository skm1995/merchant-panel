import { Route, Routes } from 'react-router';
import { ConfirmEmailPage } from './auth/ConfirmEmailPage';
import { LoginPage } from './auth/LoginPage';
import { RegisterPage } from './auth/RegisterPage';
import { HomePage } from './panel/HomePage';
import { RequireSignedIn } from './session/RequireSignedIn';

export function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/confirm" element={<ConfirmEmailPage />} />
      <Route element={<RequireSignedIn />}>
        <Route path="/" element={<HomePage />} />
      </Route>
    </Routes>
  );
}
