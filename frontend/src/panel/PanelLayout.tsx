import { NavLink, Outlet } from 'react-router';
import { useSession } from '../session/useSession';
import { Button } from '../ui/Button';
import { classNames } from '../ui/classNames';
import styles from './PanelLayout.module.css';

const LINKS = [
  { to: '/orders', label: 'Orders' },
  { to: '/products', label: 'Products' },
  { to: '/reports', label: 'Reports' },
];

export function PanelLayout() {
  const { session, signOut } = useSession();

  return (
    <>
      <header className={styles.header}>
        <nav className={styles.nav}>
          {LINKS.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              className={({ isActive }) => classNames(styles.link, isActive && styles.active)}
            >
              {link.label}
            </NavLink>
          ))}
        </nav>
        <span className={styles.user}>Signed in as {session?.email}</span>
        <Button variant="secondary" onClick={() => void signOut()}>
          Sign out
        </Button>
      </header>
      <Outlet />
    </>
  );
}
