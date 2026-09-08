export function messageFrom(error, fallback = 'Something went wrong.') {
  const payload = error?.data || error?.response?.data;
  if (payload?.errors) return Object.values(payload.errors).flat().join(' ');
  return payload?.message || error?.message || fallback;
}
