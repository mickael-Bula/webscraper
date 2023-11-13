import ApexCharts from 'apexcharts';

const generateChartOptions = function (theme, startRow, endRow) {
    return {
        series: [{
            data: apexChart.chartData,
        }],
        chart: {
            type: 'candlestick',
            width: '100%',
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
                formatter: (val) => {
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
        },
        theme: {
            mode: theme,
            palette: 'palette1',
            monochrome: {
                enabled: false,
                color: '#255aee',
                shadeTo: theme,
                shadeIntensity: 0.65
            }
        }
    };
};

const apexChart = {
    chartData: [],
    chart: null,
    initPromise: null,

    init: async () => {
        console.log("dans init");
        // Si le graphique a déjà été initialisé, renvoie une promesse résolue
        if (apexChart.chart) {
            console.log("Le graphique a déjà été initialisé");
            return Promise.resolve(apexChart.chart);
        }

        // Crée une nouvelle promesse pour l'initialisation du graphique
        apexChart.initPromise = new Promise(async (resolve, reject) => {
            console.log("dans initPromise");
            // Récupérez l'élément <div> du graphique
            const chartElement = document.getElementById('chart');

            // Vérifiez si l'élément existe et s'il contient l'attribut de données
            if (chartElement && chartElement.dataset.chartData) {
                // Analysez les données JSON du dataset
                apexChart.chartData = JSON.parse(chartElement.dataset.chartData);
            } else {
                console.log("Erreur lors de la récupération des données du Cac.");
                reject(new Error("Erreur lors de la récupération des données du Cac."));
                return;
            }

            // récupère les 200 dernières données disponibles dans le tableau (effet zoom)
            const length = apexChart.chartData.length;
            const startRow = length > 200 ? apexChart.chartData[length - 200]['x'] : apexChart.chartData[0]['x'];
            const endRow = apexChart.chartData[length - 1]['x'];

            // initialise ApexCharts avec le thème actuel
            const theme = document.body.dataset.theme;
            apexChart.initChart(generateChartOptions(theme, startRow, endRow));

            // Renvoie une promesse qui est résolue une fois le graphique rendu
            await apexChart.chart.render();
            console.log('Le rendu du graphique est terminé');
            resolve(apexChart.chart);
        });

        return apexChart.initPromise;
    },

    initChart: function (options) {
        this.chart = new ApexCharts(document.getElementById("chart"), options);
    },

    getChartInstance: function () {
        return this.chart;
    },

    destroyChart: function () {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    },

    updateChart: async function () {
        try {
            // Détruit l'instance existante du graphique
            apexChart.destroyChart();

            // Attend que l'initialisation soit complète
            await apexChart.init();

            const chart = apexChart.getChartInstance();
            const theme = document.body.dataset.theme;

            // Vérifie si le graphique est toujours disponible
            if (chart) {
                // Écoute l'événement personnalisé pour le changement de thème général
                document.body.addEventListener('themeUpdated', async () => {
                    // Ajoute un délai de 350 ms avant de mettre à jour le thème
                    setTimeout(async () => {
                        await chart.updateOptions({
                            theme: {
                                mode: theme,
                                palette: 'palette1',
                                monochrome: {
                                    enabled: false,
                                    color: '#255aee',
                                    shadeTo: theme,
                                    shadeIntensity: 0.65
                                },
                            }
                        });
                        console.log("Mise à jour du graphique complétée");
                    }, 350);
                });
            } else {
                console.error("Le graphique n'est pas disponible");
            }
        } catch (error) {
            console.error("Erreur lors de la mise à jour du graphique :", error);
        }
    }
};

document.addEventListener("DOMContentLoaded", async () => {
    await apexChart.init();
});

// Ajoute un écouteur d'événements pour le thème mis à jour
document.addEventListener('themeUpdated', async () => {
    try {
        await apexChart.updateChart();
    } catch (error) {
        console.error("Erreur lors de la mise à jour du graphique :", error);
    }
});
