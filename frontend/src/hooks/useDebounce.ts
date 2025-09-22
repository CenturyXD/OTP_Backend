import { useState, useEffect } from 'react';

/**
 * A custom hook that debounces a value.
 * @param value The value to debounce.
 * @param delay The debounce delay in milliseconds.
 * @returns The debounced value.
 */
export function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value);

  useEffect(() => {
    // ตั้งค่า timer เพื่ออัปเดตค่าหลังจาก delay
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    // ยกเลิก timer ถ้า value หรือ delay เปลี่ยนก่อน timer ทำงานเสร็จ
    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
}