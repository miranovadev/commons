import React from 'react';
import ReactDOM from 'react-dom/client';
import '@mantine/core/styles.css';
import { Provider } from 'react-redux';
import { App } from './App';
import { store } from './store';

const root = document.getElementById('app');

if (!root) throw new Error('The application root element is missing.');

ReactDOM.createRoot(root).render(
  <Provider store={store}>
    <App />
  </Provider>,
);
