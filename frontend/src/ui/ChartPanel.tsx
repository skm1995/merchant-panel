import { useId, useState, type ReactNode } from 'react';
import styles from './ChartPanel.module.css';

interface ChartPanelProps {
  title: string;
  /** A sentence or two on where the numbers come from, shown on request. */
  howItsComputed: string;
  /** The chart's numbers as a table, for screen readers, copying, and exact values. */
  data: ReactNode;
  children: ReactNode;
}

/**
 * A chart with its title, its data as a table behind a toggle, and how it is computed, so
 * every number can be read without seeing the chart.
 */
export function ChartPanel({ title, howItsComputed, data, children }: ChartPanelProps) {
  const [showData, setShowData] = useState(false);
  const titleId = useId();
  const dataId = useId();

  return (
    <figure className={styles.panel} aria-labelledby={titleId}>
      <figcaption id={titleId} className={styles.title}>
        {title}
      </figcaption>
      <div className={styles.chart}>{children}</div>
      <div className={styles.actions}>
        <button
          type="button"
          className={styles.toggle}
          aria-expanded={showData}
          aria-controls={dataId}
          onClick={() => {
            setShowData((shown) => !shown);
          }}
        >
          {showData ? 'Hide data' : 'Show data'}
        </button>
        <details className={styles.details}>
          <summary>How it&apos;s computed</summary>
          <p>{howItsComputed}</p>
        </details>
      </div>
      <div id={dataId} className={styles.data}>
        {showData && data}
      </div>
    </figure>
  );
}
