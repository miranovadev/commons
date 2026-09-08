import { createSlice } from '@reduxjs/toolkit';

const systemSlice = createSlice({
  name: 'system',
  initialState: { bootstrapRequired: false },
  reducers: {
    systemBootstrapRequired(state) {
      state.bootstrapRequired = true;
    },
    systemBootstrapped(state) {
      state.bootstrapRequired = false;
    },
  },
});

export const { systemBootstrapRequired, systemBootstrapped } = systemSlice.actions;
export default systemSlice.reducer;
