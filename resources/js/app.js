import './bootstrap';

import Alpine from 'alpinejs';
import depositForm from './deposit-form';
import saleForm from './sale-form';
import dashboardChart from './dashboard-chart';

window.Alpine = Alpine;
Alpine.data('depositForm', depositForm);
Alpine.data('saleForm', saleForm);
Alpine.data('dashboardChart', dashboardChart);
Alpine.start();
