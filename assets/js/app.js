$(document).ready(function () {
    // le dataTable ne doit être activé que si le nom de la route est app_dashboard. ce nom est enregistré dans un dataset fourni au header
    const currentRouteName = document.querySelector('header').dataset.routeName;
    console.log("le nom de la route est " + currentRouteName);
    if (currentRouteName === 'app_dashboard') {
        $('#data').DataTable();
    }
});