import { hourLabel, WEEKDAYS } from './chartFormat';
import { classNames } from './classNames';
import styles from './Heatmap.module.css';

export interface HeatmapCell {
  /** ISO weekday, 1 is Monday. */
  weekday: number;
  hour: number;
  orders: number;
}

interface HeatmapProps {
  title: string;
  cells: HeatmapCell[];
}

const HOURS = Array.from({ length: 24 }, (_, hour) => hour);
const LEVELS = [
  styles.level0,
  styles.level1,
  styles.level2,
  styles.level3,
  styles.level4,
  styles.level5,
];
const LABELLED_HOURS = new Set([0, 6, 12, 18]);

/** Shades a count from 0 (no orders) to 5 (the busiest hour) on the one-hue scale. */
function level(orders: number, busiest: number): number {
  return orders === 0 ? 0 : Math.max(1, Math.ceil((orders / busiest) * (LEVELS.length - 1)));
}

/**
 * A weekday-by-hour grid shaded by the number of orders, where every square carries its
 * exact count as its accessible name and tooltip.
 */
export function Heatmap({ title, cells }: HeatmapProps) {
  const counts = new Map(
    cells.map((cell) => [`${String(cell.weekday)}-${String(cell.hour)}`, cell.orders]),
  );
  const busiest = Math.max(1, ...cells.map((cell) => cell.orders));

  return (
    <div>
      <div className={styles.heatmap} role="group" aria-label={title}>
        <span />
        {HOURS.map((hour) => (
          <span key={hour} className={styles.hourLabel}>
            {LABELLED_HOURS.has(hour) ? hourLabel(hour) : ''}
          </span>
        ))}
        {WEEKDAYS.map((weekday, index) => (
          <Row
            key={weekday}
            weekday={weekday}
            isoWeekday={index + 1}
            counts={counts}
            busiest={busiest}
          />
        ))}
      </div>
      <div className={styles.legend} aria-hidden="true">
        <span>Fewer</span>
        {LEVELS.map((levelClass) => (
          <span key={levelClass} className={classNames(styles.legendSwatch, levelClass)} />
        ))}
        <span>More</span>
      </div>
    </div>
  );
}

interface RowProps {
  weekday: string;
  isoWeekday: number;
  counts: Map<string, number>;
  busiest: number;
}

function Row({ weekday, isoWeekday, counts, busiest }: RowProps) {
  return (
    <>
      <span className={styles.dayLabel}>{weekday}</span>
      {HOURS.map((hour) => {
        const orders = counts.get(`${String(isoWeekday)}-${String(hour)}`) ?? 0;
        const label = `${weekday} ${hourLabel(hour)}: ${String(orders)} ${orders === 1 ? 'order' : 'orders'}`;

        return (
          <span
            key={hour}
            role="img"
            aria-label={label}
            title={label}
            className={classNames(styles.cell, LEVELS[level(orders, busiest)])}
          />
        );
      })}
    </>
  );
}
