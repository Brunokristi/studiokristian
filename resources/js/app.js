import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

if (document.querySelector('#app')) {
    import('./public/main.js');
}

if (document.querySelector('#client-portal-admin')) {
    import('./backoffice/admin/main.js');
}

if (document.querySelector('#staff-workspace')) {
    import('./backoffice/staff/main.js');
}

if (document.querySelector('#client-backoffice')) {
    import('./backoffice/client/main.js');
}

if (document.querySelector('#staff-login') || document.querySelector('#client-login')) {
    import('./auth/main.js');
}
