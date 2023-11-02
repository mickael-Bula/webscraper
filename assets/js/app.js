import french from "datatables.net-plugins/i18n/fr-FR.json";
import moment from "moment";

$(function () {
    // le dataTable ne doit être activé que si le nom de la route est app_dashboard. Ce nom est enregistré dans un dataset fourni au header
    const currentRouteName = document.querySelector('header').dataset.routeName;
    console.log("le nom de la route est " + currentRouteName);
    if (currentRouteName === 'app_dashboard') {
        $('#data').DataTable({
            // utilisation de la traduction chargée depuis le module datatables.net-plugins/i18n
            language: french,
            // définition du format de date utilisé pour obtenir un tri efficace
            columnDefs: [{
                type: 'datetime-moment',
                targets: 0,
                render: function (data, type) {
                    if (type === 'sort' || type === 'type') {
                        return moment(data, 'DD/MM/YY').valueOf();
                    }
                    return data;
                }
            }]
        });
    }
});