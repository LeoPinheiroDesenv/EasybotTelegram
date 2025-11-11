import React from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';
import { Bar } from 'react-chartjs-2';
import './Charts.css';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
);

const BillingChart = ({ data }) => {
  // Dados mockados - substituir por dados reais da API
  const chartData = {
    labels: data?.labels || ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
    datasets: [
      {
        label: 'Faturamento',
        data: data?.values || [5000, 12000, 18000, 25000, 32000, 45000, 55000, 68000, 75000, 85000, 95000, 110000],
        backgroundColor: 'var(--color-light-green)',
        borderColor: 'var(--color-dark-green)',
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
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
            return `Faturamento: R$ ${context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
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
            return 'R$ ' + (value / 1000).toFixed(0) + 'k';
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

  const totalBilling = data?.total || chartData.datasets[0].data.reduce((a, b) => a + b, 0);

  return (
    <div className="chart-container">
      <div className="chart-header">
        <div className="chart-title-section">
          <h3 className="chart-title">Total de Faturamento</h3>
          <p className="chart-subtitle">Faturamento acumulado</p>
        </div>
        <div className="chart-value">
          <span className="chart-value-number">R$ {totalBilling.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
          <span className="chart-value-label">total</span>
        </div>
      </div>
      <div className="chart-content">
        <Bar data={chartData} options={options} />
      </div>
    </div>
  );
};

export default BillingChart;

