import './bootstrap';

import Alpine from 'alpinejs';
import depositForm from './deposit-form';
import saleForm from './sale-form';

window.Alpine = Alpine;
Alpine.data('depositForm', depositForm);
Alpine.data('saleForm', saleForm);
Alpine.start();
