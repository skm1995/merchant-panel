import type { ReactNode } from 'react';
import styles from './Page.module.css';

interface PageProps {
  title: string;
  children: ReactNode;
}

export function Page({ title, children }: PageProps) {
  return (
    <main className={styles.page}>
      <h1>{title}</h1>
      <div className={styles.content}>{children}</div>
    </main>
  );
}
