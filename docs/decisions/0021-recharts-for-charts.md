# 0021. Recharts for the panel's charts

Accepted, 2026-10-28.

## Context

The reports had grown to six, several over a year or more of data, and tables alone made
trends hard to see. The panel is React, styled with CSS modules under a Content-Security-Policy
that allows no inline styles or scripts.

## Decision

Draw bar and line charts with [Recharts](https://recharts.org), wrapped in the design system's
[`BarChart`](../../frontend/src/ui/BarChart.tsx) and
[`LineChart`](../../frontend/src/ui/LineChart.tsx), and the weekday-by-hour heatmap, which
Recharts has no form for, as plain markup. Pages that use charts are loaded lazily.

## Alternatives

- **Chart.js.** Smaller, but draws on a canvas, which is harder to make accessible and to
  style with the design tokens, and its API is imperative rather than React components.
- **ECharts.** Has every chart form, including heatmaps, but is far larger than six charts
  need.
- **Hand-written SVG.** No dependency, but axes, scales, and tooltips are a lot of code to own.

## Consequences

- Charts are React components styled with the same CSS variables as the rest of the panel,
  and they work under the strict Content-Security-Policy, which an end-to-end test checks.
- The library adds about 110 kB compressed, only to the report pages.
- jsdom cannot lay out SVG, so component tests read the charts' data tables; the drawing is
  checked by the end-to-end test.
