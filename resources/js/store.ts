import { configureStore } from '@reduxjs/toolkit';
import { commonsApi } from './services/commonsApi';
import systemReducer from './systemSlice';

export const store = configureStore({
  reducer: {
    [commonsApi.reducerPath]: commonsApi.reducer,
    system: systemReducer,
  },
  middleware: (getDefaultMiddleware) => getDefaultMiddleware().concat(commonsApi.middleware),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
