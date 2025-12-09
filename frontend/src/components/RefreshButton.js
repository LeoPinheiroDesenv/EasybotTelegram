import React, { useState } from 'react';
import './RefreshButton.css';

const RefreshButton = ({ onRefresh, loading = false, className = '' }) => {
  const [rotating, setRotating] = useState(false);

  const handleClick = async () => {
    setRotating(true);
    try {
      await onRefresh();
    } finally {
      // Mantém a rotação por um tempo mínimo para feedback visual
      setTimeout(() => setRotating(false), 500);
    }
  };

  return (
    <button
      onClick={handleClick}
      disabled={loading}
      className={`refresh-button ${className} ${rotating ? 'rotating' : ''}`}
      title="Atualizar dados"
    >
      <svg
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      >
        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
        <path d="M21 3v5h-5" />
        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
        <path d="M3 21v-5h5" />
      </svg>
      {loading ? 'Atualizando...' : 'Atualizar'}
    </button>
  );
};

export default RefreshButton;

