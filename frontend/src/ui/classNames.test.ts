import { classNames } from './classNames';

describe('classNames', () => {
  it('joins only the class names that apply', () => {
    expect(classNames('button', undefined, false, 'secondary')).toBe('button secondary');
  });
});
