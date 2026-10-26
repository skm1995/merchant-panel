import { useCallback, useEffect, useState } from 'react';
import { problemMessage } from '../api/problem';

export interface CursorPage<Item, Cursor> {
  items: Item[];
  nextCursor: Cursor | null;
}

export type FetchPage<Item, Cursor> = (
  cursor: Cursor | null,
) => Promise<{ page?: CursorPage<Item, Cursor>; error?: unknown }>;

/**
 * Loads the first page of a cursor-paginated list and appends later pages on request.
 */
export function useCursorPages<Item, Cursor>(fetchPage: FetchPage<Item, Cursor>) {
  const [items, setItems] = useState<Item[]>([]);
  const [nextCursor, setNextCursor] = useState<Cursor | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(true);

  const append = useCallback(
    async (cursor: Cursor | null) => {
      setBusy(true);
      const { page, error: failure } = await fetchPage(cursor);
      setBusy(false);

      if (page === undefined) {
        setError(problemMessage(failure));

        return;
      }

      setItems((current) => (cursor === null ? page.items : [...current, ...page.items]));
      setNextCursor(page.nextCursor);
    },
    [fetchPage],
  );

  useEffect(() => {
    let mounted = true;

    void fetchPage(null).then(({ page, error: failure }) => {
      if (!mounted) {
        return;
      }

      setBusy(false);

      if (page === undefined) {
        setError(problemMessage(failure));

        return;
      }

      setItems(page.items);
      setNextCursor(page.nextCursor);
    });

    return () => {
      mounted = false;
    };
  }, [fetchPage]);

  const showMore = useCallback(() => {
    if (nextCursor !== null) {
      void append(nextCursor);
    }
  }, [append, nextCursor]);

  return { items, hasMore: nextCursor !== null, showMore, error, busy };
}
