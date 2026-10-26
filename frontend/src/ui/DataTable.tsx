import type { ReactNode } from 'react';
import styles from './DataTable.module.css';

export interface Column<Row> {
  header: string;
  cell: (row: Row) => ReactNode;
}

interface DataTableProps<Row> {
  caption: string;
  columns: Column<Row>[];
  rows: Row[];
  rowKey: (row: Row) => string;
}

export function DataTable<Row>({ caption, columns, rows, rowKey }: DataTableProps<Row>) {
  return (
    <table className={styles.table}>
      <caption className={styles.caption}>{caption}</caption>
      <thead>
        <tr>
          {columns.map((column) => (
            <th key={column.header} scope="col">
              {column.header}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.length === 0 ? (
          <tr>
            <td className={styles.empty} colSpan={columns.length}>
              Nothing to show yet.
            </td>
          </tr>
        ) : (
          rows.map((row) => (
            <tr key={rowKey(row)}>
              {columns.map((column) => (
                <td key={column.header}>{column.cell(row)}</td>
              ))}
            </tr>
          ))
        )}
      </tbody>
    </table>
  );
}
