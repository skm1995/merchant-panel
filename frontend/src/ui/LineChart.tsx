import {
  CartesianGrid,
  Legend,
  Line,
  LineChart as RechartsLineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { tooltipValue } from './chartFormat';

export interface LineSeries {
  key: string;
  name: string;
}

interface LineChartProps {
  /** One point per label, holding a value under each series key. */
  data: Record<string, string | number>[];
  series: LineSeries[];
  format: (value: number) => string;
  /** Formats the value axis; defaults to format. */
  formatAxis?: (value: number) => string;
}

const HEIGHT = 280;
const FIRST_COLOR = 'var(--chart-series-1)';
const SERIES_COLORS = [FIRST_COLOR, 'var(--chart-series-2)'];
// With more points, such as a year of days, the dots merge into a thick line; the hover dot stays.
const MAX_POINTS_WITH_DOTS = 31;

/**
 * Up to two series on one axis, colored in the fixed categorical order, with a legend when
 * there is more than one and a tooltip for every point. The drawing is hidden from
 * assistive technology; ChartPanel offers the numbers as a table.
 */
export function LineChart({ data, series, format, formatAxis = format }: LineChartProps) {
  return (
    <div aria-hidden="true">
      <ResponsiveContainer
        width="100%"
        height={HEIGHT}
        initialDimension={{ width: 640, height: HEIGHT }}
      >
        <RechartsLineChart data={data} margin={{ left: 8, right: 16 }}>
          <CartesianGrid stroke="var(--chart-grid)" vertical={false} />
          <XAxis dataKey="label" stroke="var(--color-muted)" />
          <YAxis tickFormatter={formatAxis} width={88} stroke="var(--color-muted)" />
          <Tooltip formatter={tooltipValue(format)} />
          {series.length > 1 && <Legend />}
          {series.map((line, index) => {
            const color = SERIES_COLORS[index] ?? FIRST_COLOR;

            return (
              <Line
                key={line.key}
                dataKey={line.key}
                name={line.name}
                stroke={color}
                strokeWidth={2}
                dot={data.length <= MAX_POINTS_WITH_DOTS && { r: 4, fill: color }}
                activeDot={{ r: 4, fill: color }}
                isAnimationActive={false}
              />
            );
          })}
        </RechartsLineChart>
      </ResponsiveContainer>
    </div>
  );
}
