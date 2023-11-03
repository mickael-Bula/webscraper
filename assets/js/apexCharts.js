import ApexCharts from 'apexcharts';

document.addEventListener("DOMContentLoaded", function () {
    // les données sont sérialisées ainsi : Open High, Low, Close
    let chartData = [];

    // Récupérez l'élément <div> du graphique
    const chartElement = document.getElementById('chart');

    // Vérifiez si l'élément existe et s'il contient l'attribut de données
    if (chartElement && chartElement.dataset.chartData) {
        // Analysez les données JSON du dataset
        chartData = JSON.parse(chartElement.dataset.chartData);
    } else {
        console.log("Erreur lors de la récupération des données du Cac.");
    }

    // récupère les 200 dernières données disponibles dans le tableau (effet zoom)
    const length = chartData.length;
    const startRow = length > 200 ? chartData[length - 200]['x'] : chartData[0]['x'];
    const endRow = chartData[length - 1]['x'];

    const options = {
        series: [{
            data: chartData,
        }],
        chart: {
            type: 'candlestick',
            height: 350,
        },
        title: {
            text: 'Cotations quotidiennes',
            align: 'left',
        },
        xaxis: {
            type: 'datetime',
            min: startRow,
            max: endRow,
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
        // chart.zoomX(chartData[0]['x'], chartData[length - 1]['x']);
    });
});
