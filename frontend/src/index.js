import React from 'react';
import ReactDOM from 'react-dom/client';
import { config } from '@fortawesome/fontawesome-svg-core';
import '@fortawesome/fontawesome-svg-core/styles.css';
import './index.css';
import App from './App';

// Tell Font Awesome to skip adding the CSS automatically since it's already imported above
config.autoAddCss = false;

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);

