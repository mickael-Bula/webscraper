import ApexCharts from 'apexcharts';

document.addEventListener("DOMContentLoaded", function () {
    // les données sont sérialisées ainsi : Open High, Low, Close
    let chartData = [];
    console.log("apexCharts.init");

    // Récupérez l'élément <div> du graphique
    const chartElement = document.getElementById('chart');

    // Vérifiez si l'élément existe et s'il contient l'attribut de données
    if (chartElement && chartElement.dataset.chartData) {
        // Analysez les données JSON du dataset
        chartData = JSON.parse(chartElement.dataset.chartData);
    } else {
        console.log("Erreur lors de la récupération des données du Cac.");
    }

    const options = {
        series: [{
            data: chartData,
        }],
        chart: {
            type: 'candlestick',
            height: 350
        },
        title: {
            text: 'Cotations quotidiennes',
            align: 'left'
        },
        xaxis: {
            type: 'datetime',
            labels: {
                formatter: function (val) {
                        return new Date(val).toLocaleString("fr-FR", {
                            year: '2-digit',
                            month: '2-digit',
                            day: '2-digit'
                        });
                    }
                }
            },
        yaxis: {
            tooltip: {
                enabled: true
            }
        }
    }

    const chart = new ApexCharts(document.getElementById("chart"), options);

    chart.render().then(() => {
        console.log('Chart rendering complete');
    });
});
