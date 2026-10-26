import { useId } from 'react';
import styles from './TextField.module.css';

interface TextFieldProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: 'text' | 'email' | 'password' | 'date';
  error?: string;
  autoComplete?: string;
}

export function TextField({
  label,
  value,
  onChange,
  type = 'text',
  error,
  autoComplete,
}: TextFieldProps) {
  const id = useId();
  const errorId = `${id}-error`;

  return (
    <div className={styles.field}>
      <label htmlFor={id}>{label}</label>
      <input
        id={id}
        className={styles.input}
        type={type}
        value={value}
        autoComplete={autoComplete}
        aria-invalid={error !== undefined}
        aria-describedby={error === undefined ? undefined : errorId}
        onChange={(event) => {
          onChange(event.target.value);
        }}
      />
      {error !== undefined && (
        <span id={errorId} className={styles.error}>
          {error}
        </span>
      )}
    </div>
  );
}
