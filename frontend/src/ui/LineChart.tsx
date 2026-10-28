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
}

const HEIGHT = 280;
const FIRST_COLOR = 'var(--chart-series-1)';
const SERIES_COLORS = [FIRST_COLOR, 'var(--chart-series-2)'];

/**
 * Up to two series on one axis, colored in the fixed categorical order, with a legend when
 * there is more than one and a tooltip for every point. The drawing is hidden from
 * assistive technology; ChartPanel offers the numbers as a table.
 */
export function LineChart({ data, series, format }: LineChartProps) {
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
          <YAxis tickFormatter={format} width={88} stroke="var(--color-muted)" />
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
                dot={{ r: 4, fill: color }}
                isAnimationActive={false}
              />
            );
          })}
        </RechartsLineChart>
      </ResponsiveContainer>
    </div>
  );
}
