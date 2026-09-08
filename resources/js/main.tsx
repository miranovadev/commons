import React from 'react';
import ReactDOM from 'react-dom/client';
import '@mantine/core/styles.css';
import { Provider } from 'react-redux';
import { App } from './App';
import { store } from './store';

ReactDOM.createRoot(document.getElementById('app')).render(
  <Provider store={store}>
    <App />
  </Provider>,
);
