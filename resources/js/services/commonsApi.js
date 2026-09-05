import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react';
import { systemBootstrapRequired } from '../systemSlice';
import { BOOTSTRAP_REQUIRED, HTTP_PRECONDITION_REQUIRED, HTTP_SESSION_EXPIRED } from '../http';

const rawBaseQuery = fetchBaseQuery({
  baseUrl: '/api',
  credentials: 'include',
  prepareHeaders: (headers) => {
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const token = document.cookie
      .split('; ')
      .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
      ?.split('=')
      .slice(1)
      .join('=');

    if (token) headers.set('X-XSRF-TOKEN', decodeURIComponent(token));

    return headers;
  },
});

let csrfPromise;

function isMutation(args) {
  const method = typeof args === 'string' ? 'GET' : (args.method || 'GET');
  return !['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase());
}

async function prepareCsrf() {
  csrfPromise ??= fetch('/api/csrf-cookie', {
    credentials: 'include',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  }).then((response) => {
    if (!response.ok) throw new Error('Unable to initialise CSRF protection.');
  }).catch((error) => {
    csrfPromise = undefined;
    throw error;
  });

  return csrfPromise;
}

const baseQuery = async (args, api, extraOptions) => {
  if (isMutation(args)) await prepareCsrf();

  let result = await rawBaseQuery(args, api, extraOptions);

  if (result.error?.status === HTTP_SESSION_EXPIRED && isMutation(args)) {
    csrfPromise = undefined;
    await prepareCsrf();
    result = await rawBaseQuery(args, api, extraOptions);
  }

  if (
    result.error?.status === HTTP_PRECONDITION_REQUIRED
    && result.error.data?.code === BOOTSTRAP_REQUIRED
  ) {
    api.dispatch(systemBootstrapRequired());
  }

  return result;
};

const data = (response) => response.data;

export const commonsApi = createApi({
  reducerPath: 'commonsApi',
  baseQuery,
  tagTypes: ['Session', 'Substrate', 'Participant', 'Connection'],
  endpoints: (builder) => ({
    getSession: builder.query({ query: () => '/session', transformResponse: data, providesTags: ['Session'] }),
    login: builder.mutation({ query: (body) => ({ url: '/session', method: 'POST', body }), transformResponse: data, invalidatesTags: ['Session', 'Substrate'] }),
    logout: builder.mutation({ query: () => ({ url: '/session', method: 'DELETE' }), invalidatesTags: ['Session', 'Substrate'] }),
    bootstrap: builder.mutation({ query: (body) => ({ url: '/bootstrap', method: 'POST', body }), transformResponse: data, invalidatesTags: ['Session', 'Substrate'] }),
    getSubstrates: builder.query({ query: () => '/substrates', transformResponse: data, providesTags: (result) => result ? [...result.map(({ id }) => ({ type: 'Substrate', id })), { type: 'Substrate', id: 'LIST' }] : [{ type: 'Substrate', id: 'LIST' }] }),
    createSubstrate: builder.mutation({ query: (body) => ({ url: '/substrates', method: 'POST', body }), transformResponse: data, invalidatesTags: [{ type: 'Substrate', id: 'LIST' }] }),
    getSubstrate: builder.query({ query: (slug) => `/substrates/${slug}`, transformResponse: data, providesTags: (result) => result ? [{ type: 'Substrate', id: result.id }] : [] }),
    getPropositions: builder.query({ query: (slug) => `/substrates/${slug}/propositions`, transformResponse: data }),
    getParticipants: builder.query({ query: (slug) => `/substrates/${slug}/participants`, transformResponse: data, providesTags: (result) => result ? [...result.map(({ id }) => ({ type: 'Participant', id })), { type: 'Participant', id: 'LIST' }] : [{ type: 'Participant', id: 'LIST' }] }),
    joinParticipant: builder.mutation({ query: ({ slug, ...body }) => ({ url: `/substrates/${slug}/participants`, method: 'POST', body }), transformResponse: data, invalidatesTags: [{ type: 'Participant', id: 'LIST' }, { type: 'Substrate', id: 'LIST' }] }),
    getParticipant: builder.query({ query: ({ slug, participantId }) => `/substrates/${slug}/participants/${participantId}`, transformResponse: data }),
    getParticipantPlan: builder.query({ query: ({ slug, participantId }) => `/substrates/${slug}/participants/${participantId}/plan`, transformResponse: data }),
    getConnectionOptions: builder.query({ query: () => '/connection-options', transformResponse: data }),
    getConnections: builder.query({ query: ({ slug, participantId }) => `/substrates/${slug}/participants/${participantId}/connections`, transformResponse: data, providesTags: (result) => result ? [...result.map(({ id }) => ({ type: 'Connection', id })), { type: 'Connection', id: 'LIST' }] : [{ type: 'Connection', id: 'LIST' }] }),
    createConnection: builder.mutation({ query: ({ slug, participantId, ...body }) => ({ url: `/substrates/${slug}/participants/${participantId}/connections`, method: 'POST', body }), invalidatesTags: [{ type: 'Connection', id: 'LIST' }] }),
    updateConnection: builder.mutation({ query: ({ slug, participantId, connectionId, ...body }) => ({ url: `/substrates/${slug}/participants/${participantId}/connections/${connectionId}`, method: 'PATCH', body }), invalidatesTags: (_result, _error, { connectionId }) => [{ type: 'Connection', id: connectionId }] }),
    deleteConnection: builder.mutation({ query: ({ slug, participantId, connectionId }) => ({ url: `/substrates/${slug}/participants/${participantId}/connections/${connectionId}`, method: 'DELETE' }), invalidatesTags: (_result, _error, { connectionId }) => [{ type: 'Connection', id: connectionId }, { type: 'Connection', id: 'LIST' }] }),
  }),
});

export const {
  useBootstrapMutation,
  useCreateConnectionMutation,
  useCreateSubstrateMutation,
  useDeleteConnectionMutation,
  useGetConnectionOptionsQuery,
  useGetConnectionsQuery,
  useGetParticipantPlanQuery,
  useGetParticipantQuery,
  useGetParticipantsQuery,
  useGetPropositionsQuery,
  useGetSessionQuery,
  useGetSubstrateQuery,
  useGetSubstratesQuery,
  useJoinParticipantMutation,
  useLoginMutation,
  useLogoutMutation,
  useUpdateConnectionMutation,
} = commonsApi;
