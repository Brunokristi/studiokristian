import { createRouter, createWebHistory } from 'vue-router';
import Home from '../pages/Home.vue';

const routes = [
    {
        path: '/',
        name: 'home',
        component: Home,
        meta: {
            theme: 'light',
            footer: true,
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
