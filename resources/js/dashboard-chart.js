import Chart from 'chart.js/auto';

export default (series) => ({
    chart: null,
    init() {
        this.chart = new Chart(this.$refs.canvas, {
            type: 'line',
            data: {
                labels: series.labels,
                datasets: [
                    { label: 'Nilai setoran', data: series.deposits, borderColor: '#047857', backgroundColor: '#a7f3d0', tension: 0.25 },
                    { label: 'Nilai penjualan', data: series.sales, borderColor: '#0f766e', backgroundColor: '#99f6e4', tension: 0.25 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                scales: { y: { beginAtZero: true, ticks: { callback: (value) => 'Rp ' + Number(value).toLocaleString('id-ID') } } },
                plugins: { tooltip: { callbacks: { label: (context) => context.dataset.label + ': Rp ' + Number(context.raw).toLocaleString('id-ID') } } },
            },
        });
    },
    destroy() {
        this.chart?.destroy();
    },
});
