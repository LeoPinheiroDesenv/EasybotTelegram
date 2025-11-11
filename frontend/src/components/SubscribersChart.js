import React from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import { Line } from 'react-chartjs-2';
import './Charts.css';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const SubscribersChart = ({ data }) => {
  // Dados mockados - substituir por dados reais da API
  const chartData = {
    labels: data?.labels || ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
    datasets: [
      {
        label: 'Assinantes',
        data: data?.values || [120, 190, 300, 500, 800, 1200, 1500, 1800, 2100, 2400, 2700, 3000],
        borderColor: 'var(--color-dark-green)',
        backgroundColor: 'rgba(30, 126, 52, 0.1)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: 'var(--color-dark-green)',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
        pointHoverBackgroundColor: 'var(--color-light-green)',
        pointHoverBorderColor: '#ffffff',
      },
    ],
  };

  const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: false,
      },
      tooltip: {
        backgroundColor: 'rgba(0, 0, 0, 0.8)',
        padding: 12,
        titleFont: {
          size: 14,
          weight: 'bold',
        },
        bodyFont: {
          size: 13,
        },
        callbacks: {
          label: function(context) {
            return `Assinantes: ${context.parsed.y.toLocaleString('pt-BR')}`;
          }
        }
      },
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: 'rgba(0, 0, 0, 0.05)',
        },
        ticks: {
          color: 'var(--text-light)',
          font: {
            size: 12,
          },
          callback: function(value) {
            return value.toLocaleString('pt-BR');
          }
        },
      },
      x: {
        grid: {
          display: false,
        },
        ticks: {
          color: 'var(--text-light)',
          font: {
            size: 12,
          },
        },
      },
    },
  };

  const totalSubscribers = data?.total || chartData.datasets[0].data[chartData.datasets[0].data.length - 1];

  return (
    <div className="chart-container">
      <div className="chart-header">
        <div className="chart-title-section">
          <h3 className="chart-title">Assinantes</h3>
          <p className="chart-subtitle">Total de assinantes ativos</p>
        </div>
        <div className="chart-value">
          <span className="chart-value-number">{totalSubscribers.toLocaleString('pt-BR')}</span>
          <span className="chart-value-label">assinantes</span>
        </div>
      </div>
      <div className="chart-content">
        <Line data={chartData} options={options} />
      </div>
    </div>
  );
};

export default SubscribersChart;

