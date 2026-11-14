import Chart from 'chart.js/auto';

const NAVY = '#0F2A4A';
const TEAL = '#14B8A6';
const PALETTES = {
    occupancy: ['#17396A', '#14B8A6', '#CBD5E1'],
    reservations: ['#10B981', '#F59E0B', '#F43F5E', '#94A3B8'],
};

const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA';

function configFor(kind, data) {
    if (kind === 'revenue') {
        return {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: "Chiffre d'affaires",
                    data: data.values,
                    borderColor: TEAL,
                    backgroundColor: 'rgba(20,184,166,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: data.values.length > 40 ? 0 : 3,
                    pointBackgroundColor: NAVY,
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => fcfa(c.parsed.y) } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#EEF2F6' },
                        ticks: { callback: (v) => new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(v) },
                    },
                },
            },
        };
    }

    return {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{ data: data.values, backgroundColor: PALETTES[kind], borderWidth: 2, borderColor: '#fff' }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, padding: 14 } } },
        },
    };
}

document.addEventListener('alpine:init', () => {
    // <div x-data="hpChart('revenue', @js($data))" @dashboard-updated.window="refresh($event.detail.charts)">
    window.Alpine.data('hpChart', (kind, initial) => {
        // L'instance Chart.js reste hors de la réactivité d'Alpine (le proxy provoquait « Maximum call stack size exceeded »).
        let chart = null;

        return {
            init() {
                if (! initial) return;
                chart = new Chart(this.$refs.canvas, configFor(kind, initial));
            },
            refresh(all) {
                const data = all?.[kind];
                if (! data) return;
                if (! chart) {
                    chart = new Chart(this.$refs.canvas, configFor(kind, data));
                    return;
                }
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.values;
                if (kind === 'revenue') {
                    chart.data.datasets[0].pointRadius = data.values.length > 40 ? 0 : 3;
                }
                chart.update();
            },
            destroy() {
                chart?.destroy();
                chart = null;
            },
        };
    });
});
