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
import { categoryWidth, fitLabel, tooltipValue } from './chartFormat';

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
const LABEL_OFFSET = 6;
interface CategoryTickProps {
  x?: number | string | undefined;
  y?: number | string | undefined;
  payload?: { value?: string | number } | undefined;
  width: number;
}

/**
 * A category name on one line; the library's own ticks wrap long names onto the next row.
 */
function CategoryTick({ x = 0, y = 0, payload, width }: CategoryTickProps) {
  const label = String(payload?.value ?? '');

  return (
    <text x={x} y={y} dy="0.35em" textAnchor="end" fill="var(--color-muted)">
      <title>{label}</title>
      {fitLabel(label, width)}
    </text>
  );
}

interface ValueLabelProps {
  x?: number | string | undefined;
  y?: number | string | undefined;
  width?: number | string | undefined;
  height?: number | string | undefined;
  value?: unknown;
}

/**
 * One series of bars in the first chart color, with a recessive grid and a tooltip. The
 * drawing is hidden from assistive technology; ChartPanel offers the numbers as a table.
 */
export function BarChart({ data, format, formatAxis = format, horizontal = false }: BarChartProps) {
  const height = horizontal ? Math.max(HEIGHT / 2, data.length * ROW_HEIGHT + 40) : HEIGHT;
  const labelWidth = categoryWidth(data.map((datum) => datum.label));
  // Drawn by hand, so a bar of zero, such as a product already sold out, still shows its value.
  const valueLabel = ({
    x = 0,
    y = 0,
    width = 0,
    height: barHeight = 0,
    value,
  }: ValueLabelProps) => (
    <text
      x={Number(x) + Number(width) + LABEL_OFFSET}
      y={Number(y) + Number(barHeight) / 2}
      dy="0.35em"
      fill="var(--color-text)"
    >
      {format(Number(value))}
    </text>
  );

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
              <YAxis
                type="category"
                dataKey="label"
                width={labelWidth}
                interval={0}
                tick={(props: Omit<CategoryTickProps, 'width'>) => (
                  <CategoryTick {...props} width={labelWidth} />
                )}
                stroke="var(--color-muted)"
              />
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
            {horizontal && <LabelList dataKey="value" content={valueLabel} />}
          </Bar>
        </RechartsBarChart>
      </ResponsiveContainer>
    </div>
  );
}
