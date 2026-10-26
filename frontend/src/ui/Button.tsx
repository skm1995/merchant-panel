import type { ReactNode } from 'react';
import styles from './Button.module.css';
import { classNames } from './classNames';

interface ButtonProps {
  children: ReactNode;
  type?: 'button' | 'submit';
  variant?: 'primary' | 'secondary';
  busy?: boolean;
  onClick?: () => void;
}

export function Button({
  children,
  type = 'button',
  variant = 'primary',
  busy = false,
  onClick,
}: ButtonProps) {
  return (
    <button
      type={type}
      className={classNames(styles.button, variant === 'secondary' && styles.secondary)}
      disabled={busy}
      aria-busy={busy}
      onClick={onClick}
    >
      {children}
    </button>
  );
}
