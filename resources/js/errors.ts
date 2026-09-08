interface ErrorPayload {
  message?: string;
  errors?: Record<string, string[]>;
}

function record(value: unknown): Record<string, unknown> | undefined {
  return typeof value === 'object' && value !== null
    ? value as Record<string, unknown>
    : undefined;
}

export function messageFrom(error: unknown, fallback = 'Something went wrong.'): string {
  const source = record(error);
  const response = record(source?.response);
  const payload = (record(source?.data) || record(response?.data)) as ErrorPayload | undefined;
  if (payload?.errors) return Object.values(payload.errors).flat().join(' ');
  return payload?.message || (typeof source?.message === 'string' ? source.message : fallback);
}
