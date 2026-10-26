import type { ReactNode } from 'react';
import styles from './Alert.module.css';
import { classNames } from './classNames';

interface AlertProps {
  tone: 'error' | 'success';
  children: ReactNode;
}

/**
 * Errors interrupt screen readers immediately; confirmations wait for a pause.
 */
export function Alert({ tone, children }: AlertProps) {
  return (
    <div
      role={tone === 'error' ? 'alert' : 'status'}
      className={classNames(styles.alert, styles[tone])}
    >
      {children}
    </div>
  );
}
