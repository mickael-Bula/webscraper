/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// ensuite import de bootstrap, suivi de ses extensions
import 'bootstrap';
import 'datatables.net';
import 'datatables.net-bs5';

// any CSS you import will output into a single css file (app.css in this case)
import './styles/app.scss';
import './styles/scroll-up.scss';

// import de mes fichiers js qui, placés ici, sont compilés sans problèmes
import './js/app.js';
import './js/scroll-up.js';

// import des images pour qu'elles soient compilées par wabpack
import './images/stocks.jpg';