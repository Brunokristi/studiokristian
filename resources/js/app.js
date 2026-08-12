import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

if (document.querySelector('#app')) {
    import('./main.js');
}

if (document.querySelector('#client-portal-admin')) {
    import('./admin/client-portal/main.js');
}
