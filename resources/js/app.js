import './bootstrap';

import Alpine from 'alpinejs';
import depositForm from './deposit-form';

window.Alpine = Alpine;
Alpine.data('depositForm', depositForm);
Alpine.start();
