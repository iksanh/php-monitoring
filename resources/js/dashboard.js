import Chart from 'chart.js/auto';

/*
 * Grafik dashboard.
 *
 * Konfigurasi tiap grafik dikirim dari Blade lewat atribut data-grafik, jadi
 * seluruh angka tetap dihitung di DashboardService, bukan di peramban.
 */

const TINTA = '#0b0b0b';
const TINTA_REDUP = '#52514e';
const GARIS = '#e5e5e5';
const PERMUKAAN = '#ffffff';

Chart.defaults.font.family =
    'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif';
Chart.defaults.font.size = window.innerWidth < 640 ? 11 : 12;
Chart.defaults.color = TINTA_REDUP;

/**
 * Memecah label panjang menjadi beberapa baris.
 *
 * Nama tahap dan nama instansi datang dari data — panjangnya tidak bisa
 * diatur di sini — sedangkan di ponsel sumbu kiri hanya kebagian sedikit
 * ruang. Tanpa ini Chart.js memotong teksnya menjadi "…n Kabupaten Sukamaju".
 */
function bungkusLabel(teks, maksimal) {
    const kata = String(teks).split(' ');
    const baris = [];
    let berjalan = '';

    kata.forEach(function (potong) {
        const calon = berjalan === '' ? potong : berjalan + ' ' + potong;

        if (calon.length > maksimal && berjalan !== '') {
            baris.push(berjalan);
            berjalan = potong;

            return;
        }

        berjalan = calon;
    });

    if (berjalan !== '') {
        baris.push(berjalan);
    }

    return baris;
}

/**
 * Lebar label mengikuti lebar layar: makin sempit, makin cepat dipatahkan.
 */
function batasLabel() {
    if (window.innerWidth < 480) {
        return 14;
    }

    return window.innerWidth < 1024 ? 22 : 34;
}

function sumbuLabelPanjang() {
    return {
        autoSkip: false,
        padding: 8,
        callback: function (nilai) {
            return bungkusLabel(this.getLabelForValue(nilai), batasLabel());
        },
    };
}

/**
 * Menulis nilai di ujung batang. Bila teks tidak muat di dalam batang, teks
 * dipindah ke luar ujungnya — tidak pernah dipotong.
 */
const labelUjung = {
    id: 'labelUjung',
    afterDatasetsDraw(chart) {
        const { ctx } = chart;

        chart.data.datasets.forEach((dataset, indeks) => {
            if (! dataset.labelUjung) {
                return;
            }

            const meta = chart.getDatasetMeta(indeks);

            meta.data.forEach((batang, i) => {
                const nilai = dataset.data[i];

                if (! nilai) {
                    return;
                }

                const teks = dataset.formatLabel ? dataset.formatLabel(nilai) : String(nilai);

                ctx.save();
                ctx.font = '600 12px ' + Chart.defaults.font.family;
                ctx.textBaseline = 'middle';

                const lebarTeks = ctx.measureText(teks).width;
                const pangkal = chart.scales.x.getPixelForValue(0);
                const lebarBatang = batang.x - pangkal;
                const muat = lebarBatang > lebarTeks + 16;

                ctx.fillStyle = muat ? PERMUKAAN : TINTA;
                ctx.textAlign = muat ? 'right' : 'left';
                ctx.fillText(teks, muat ? batang.x - 8 : batang.x + 8, batang.y);
                ctx.restore();
            });
        });
    },
};

/**
 * Angka besar di tengah donat.
 */
const intiDonat = {
    id: 'intiDonat',
    afterDraw(chart) {
        const inti = chart.options.plugins?.intiDonat;

        // Chart.js menyalakan setiap plugin terdaftar untuk semua grafik dan
        // tetap menyediakan objek opsi meski kosong. Jadi yang diperiksa
        // adalah muatannya, bukan ada atau tidaknya objek itu.
        if (! inti?.angka) {
            return;
        }

        const { ctx, chartArea } = chart;
        const x = (chartArea.left + chartArea.right) / 2;
        const y = (chartArea.top + chartArea.bottom) / 2;

        ctx.save();
        ctx.textAlign = 'center';

        ctx.fillStyle = TINTA;
        ctx.font = '600 28px ' + Chart.defaults.font.family;
        ctx.textBaseline = 'alphabetic';
        ctx.fillText(inti.angka, x, y + 4);

        ctx.fillStyle = TINTA_REDUP;
        ctx.font = '12px ' + Chart.defaults.font.family;
        ctx.textBaseline = 'top';
        ctx.fillText(inti.keterangan, x, y + 12);

        ctx.restore();
    },
};

Chart.register(labelUjung, intiDonat);

const dasarBatang = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    layout: { padding: { right: 28 } },
    scales: {
        x: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: GARIS, drawTicks: false },
            ticks: { precision: 0, padding: 8 },
        },
        y: {
            border: { display: false },
            grid: { display: false },
            ticks: sumbuLabelPanjang(),
        },
    },
};

const bentuk = {
    tertahan(data) {
        return {
            type: 'bar',
            data: {
                labels: data.label,
                datasets: [{
                    label: 'Bidang tertahan',
                    data: data.nilai,
                    backgroundColor: data.warna,
                    maxBarThickness: 24,
                    borderRadius: 4,
                    borderSkipped: 'start',
                    labelUjung: true,
                }],
            },
            options: {
                ...dasarBatang,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (item) => `${item.parsed.x} bidang` } },
                },
            },
        };
    },

    penanggungJawab(data) {
        return {
            type: 'doughnut',
            data: {
                labels: data.label,
                datasets: [{
                    data: data.nilai,
                    backgroundColor: data.warna,
                    borderColor: PERMUKAAN,
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle', padding: 16 },
                    },
                    tooltip: { callbacks: { label: (item) => `${item.label}: ${item.parsed} bidang` } },
                    intiDonat: data.inti,
                },
            },
        };
    },

    capaian(data) {
        return {
            type: 'bar',
            data: {
                labels: data.label,
                datasets: [
                    {
                        label: 'Sudah bersertipikat',
                        data: data.selesai,
                        backgroundColor: data.warna[0],
                        maxBarThickness: 24,
                        borderColor: PERMUKAAN,
                        borderWidth: { right: 2 },
                        labelUjung: true,
                    },
                    {
                        label: 'Belum',
                        data: data.belum,
                        backgroundColor: data.warna[1],
                        maxBarThickness: 24,
                        borderRadius: 4,
                        borderSkipped: 'start',
                    },
                ],
            },
            options: {
                ...dasarBatang,
                scales: {
                    ...dasarBatang.scales,
                    x: { ...dasarBatang.scales.x, stacked: true },
                    y: { ...dasarBatang.scales.y, stacked: true },
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle', padding: 16 },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (item) {
                                const total = data.selesai[item.dataIndex] + data.belum[item.dataIndex];
                                const persen = total > 0 ? Math.round((item.parsed.x / total) * 100) : 0;

                                return `${item.dataset.label}: ${item.parsed.x} dari ${total} bidang (${persen}%)`;
                            },
                        },
                    },
                },
            },
        };
    },
};

document.querySelectorAll('canvas[data-grafik]').forEach((kanvas) => {
    const data = JSON.parse(kanvas.dataset.grafik);
    const susun = bentuk[data.bentuk];

    if (! susun) {
        return;
    }

    new Chart(kanvas, susun(data));
});
