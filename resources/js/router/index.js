import { createRouter, createWebHistory } from 'vue-router';
import Home from '../pages/Home.vue';
import Nav from '../pages/Navigation.vue';
import Portfolio from '../pages/Portfolio.vue';
import Project from '../pages/Project.vue';
import Workflow from '../pages/Workflow.vue';
import Pricing from '../pages/Pricing.vue';
import Contact from '../pages/Contact.vue';
import PrivacyPolicy from '../pages/PrivacyPolicy.vue';

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
    {
        path: '/nav',
        name: 'nav',
        component: Nav,
        meta: {
            theme: 'dark',
            footer: false,
        },
    },
    {
        path: '/portfolio',
        name: 'portfolio',
        component: Portfolio,
        meta: {
            theme: 'light',
            footer: true,
        },
    },
    {
        path: '/portfolio/:url',
        name: 'project',
        component: Project,
        meta: {
            theme: 'light',
            footer: true,
        },
    },
    {
        path: '/workflow',
        name: 'workflow',
        component: Workflow,
        meta: {
            theme: 'light',
            footer: true,
        },
    },
    {
        path: '/pricing',
        name: 'pricing',
        component: Pricing,
        meta: {
            theme: 'light',
            footer: true,
        },
    },
    {
        path: '/contact',
        name: 'contact',
        component: Contact,
        meta: {
            theme: 'dark',
            footer: true,
        },
    },
    {
        path: '/privacy-policy',
        name: 'privacy-policy',
        component: PrivacyPolicy,
        meta: {
            theme: 'light',
            footer: true,
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

export default router;
