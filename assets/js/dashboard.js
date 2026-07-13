/**
 * Dashboard charts
 */
(function () {
  if (typeof Chart === 'undefined' || !window.DASHBOARD_DATA) return;

  const d = window.DASHBOARD_DATA;
  const pieEl = document.getElementById('chart-pie');
  const barEl = document.getElementById('chart-bar');

  if (pieEl) {
    const crv = d.pie.crv || 0;
    const cpv = d.pie.cpv || 0;
    const empty = crv === 0 && cpv === 0;
    new Chart(pieEl, {
      type: 'doughnut',
      data: {
        labels: ['Cash Receipts (CRV)', 'Cash Payments (CPV)'],
        datasets: [{
          data: empty ? [1, 1] : [crv, cpv],
          backgroundColor: empty
            ? ['#e2e8f0', '#cbd5e1']
            : ['#10b981', '#f43f5e'],
          borderWidth: 0,
          hoverOffset: 6,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { family: 'Plus Jakarta Sans', size: 12 } } },
          tooltip: { enabled: !empty },
        },
      },
    });
  }

  if (barEl) {
    new Chart(barEl, {
      type: 'bar',
      data: {
        labels: d.bar.labels,
        datasets: [
          {
            label: 'Receipts',
            data: d.bar.crv,
            backgroundColor: 'rgba(16,185,129,.85)',
            borderRadius: 8,
            maxBarThickness: 28,
          },
          {
            label: 'Payments',
            data: d.bar.cpv,
            backgroundColor: 'rgba(244,63,94,.8)',
            borderRadius: 8,
            maxBarThickness: 28,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: 'rgba(226,232,240,.8)' } },
        },
        plugins: {
          legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { family: 'Plus Jakarta Sans', size: 12 } } },
        },
      },
    });
  }
})();
