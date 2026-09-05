import axios from 'axios';

export const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  withCredentials: true,
});

export async function csrf() {
  await api.get('/csrf-cookie');
}

export function messageFrom(error, fallback = 'Something went wrong.') {
  const errors = error?.response?.data?.errors;
  if (errors) return Object.values(errors).flat().join(' ');
  return error?.response?.data?.message || fallback;
}
