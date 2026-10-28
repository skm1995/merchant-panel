import {
  LabelList,
  Bar,
  BarChart as RechartsBarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { tooltipValue } from './chartFormat';

export interface BarDatum {
  label: string;
  value: number;
}

interface BarChartProps {
  data: BarDatum[];
  /** Formats values in the tooltip and next to the bars. */
  format: (value: number) => string;
  /** Formats the value axis; defaults to format. */
  formatAxis?: (value: number) => string;
  /** Horizontal bars suit long category names, such as products. */
  horizontal?: boolean;
}

const HEIGHT = 280;
const ROW_HEIGHT = 32;
const ROUNDED_END = 4;
// Room right of the longest bar for its value label, such as "PLN 5,654,320".
const LABEL_SPACE = 112;

/**
 * One series of bars in the first chart color, with a recessive grid and a tooltip. The
 * drawing is hidden from assistive technology; ChartPanel offers the numbers as a table.
 */
export function BarChart({ data, format, formatAxis = format, horizontal = false }: BarChartProps) {
  const height = horizontal ? Math.max(HEIGHT / 2, data.length * ROW_HEIGHT + 40) : HEIGHT;

  return (
    <div aria-hidden="true">
      <ResponsiveContainer width="100%" height={height} initialDimension={{ width: 640, height }}>
        <RechartsBarChart
          data={data}
          layout={horizontal ? 'vertical' : 'horizontal'}
          margin={{ left: 8, right: horizontal ? LABEL_SPACE : 16 }}
        >
          <CartesianGrid
            stroke="var(--chart-grid)"
            vertical={horizontal}
            horizontal={!horizontal}
          />
          {horizontal ? (
            <>
              <XAxis type="number" tickFormatter={formatAxis} stroke="var(--color-muted)" />
              <YAxis type="category" dataKey="label" width={160} stroke="var(--color-muted)" />
            </>
          ) : (
            <>
              <XAxis dataKey="label" stroke="var(--color-muted)" />
              <YAxis tickFormatter={formatAxis} width={88} stroke="var(--color-muted)" />
            </>
          )}
          <Tooltip formatter={tooltipValue(format)} cursor={{ fill: 'var(--chart-grid)' }} />
          <Bar
            dataKey="value"
            fill="var(--chart-series-1)"
            radius={
              horizontal ? [0, ROUNDED_END, ROUNDED_END, 0] : [ROUNDED_END, ROUNDED_END, 0, 0]
            }
            isAnimationActive={false}
          >
            {horizontal && (
              <LabelList
                dataKey="value"
                position="right"
                formatter={tooltipValue(format)}
                fill="var(--color-text)"
              />
            )}
          </Bar>
        </RechartsBarChart>
      </ResponsiveContainer>
    </div>
  );
}
