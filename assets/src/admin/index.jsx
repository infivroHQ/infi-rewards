/**
 * React admin app entry (stub).
 * Developers should run a build tool to compile into assets/build.
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

function App() {
  return (
    <div>
      <h1>infiRewards Admin (stub)</h1>
    </div>
  );
}

const container = document.getElementById('infirewards-admin-app');
if (container) {
  const root = createRoot(container);
  root.render(<App />);
}
