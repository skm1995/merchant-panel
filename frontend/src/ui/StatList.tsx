import styles from './StatList.module.css';

export interface Stat {
  label: string;
  value: string;
}

interface StatListProps {
  label: string;
  stats: Stat[];
}

/**
 * A row of headline numbers, each with its label above it.
 */
export function StatList({ label, stats }: StatListProps) {
  return (
    <ul className={styles.list} aria-label={label}>
      {stats.map((stat) => (
        <li key={stat.label} className={styles.stat}>
          <span className={styles.label}>{stat.label}</span>
          <span className={styles.value}>{stat.value}</span>
        </li>
      ))}
    </ul>
  );
}
