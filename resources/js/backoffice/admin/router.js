import { createRouter, createWebHistory } from 'vue-router'

import Dashboard from './pages/Dashboard.vue'

import ClientsIndex from './pages/clients/ClientsIndex.vue'
import ClientLayout from './pages/clients/ClientLayout.vue'
import ClientInformation from './pages/clients/ClientInformation.vue'
import ClientContacts from './pages/clients/ClientContacts.vue'
import ClientProjects from './pages/clients/ClientProjects.vue'
import ClientDangerZone from './pages/clients/ClientDangerZone.vue'

import ProjectLayout from './pages/projects/ProjectLayout.vue'
import ProjectCreate from './pages/projects/ProjectCreate.vue'
import ProjectInformation from './pages/projects/ProjectInformation.vue'
import ProjectPeople from './pages/projects/ProjectPeople.vue'
import ProjectTickets from './pages/projects/ProjectTickets.vue'
import ProjectFiles from './pages/projects/ProjectFiles.vue'
import ProjectDangerZone from './pages/projects/ProjectDangerZone.vue'
import ProjectBilling from './pages/projects/ProjectBilling.vue'
import ProjectsIndex from './pages/projects/ProjectsIndex.vue'

import ServiceProductsIndex from './pages/service-products/ServiceProductsIndex.vue'
import ServiceProductDetail from './pages/service-products/ServiceProductDetail.vue'

import PortfolioIndex from './pages/portfolio/PortfolioIndex.vue'
import PortfolioDetail from './pages/portfolio/PortfolioDetail.vue'

import CoworkersIndex from './pages/coworkers/CoworkersIndex.vue'
import CoworkerDetail from './pages/coworkers/CoworkerDetail.vue'

import InternalStorage from './pages/internal-storage/InternalStorage.vue'

import SaasIndex from './pages/saas/SaasIndex.vue'
import SaasDetail from './pages/saas/SaasDetail.vue'
import SaasCustomerDetail from './pages/saas/SaasCustomerDetail.vue'

const router = createRouter({

    history: createWebHistory('/admin/client-portal/'),

    routes: [

        {
            path: '/',
            name: 'dashboard',
            component: Dashboard,
        },

        {
            path: '/clients',
            name: 'clients.index',
            component: ClientsIndex,
        },

        {
            path: '/clients/create',
            name: 'clients.create',
            component: ClientInformation,
        },

        {
            path: '/clients/:id',
            component: ClientLayout,
            props: true,
            children: [
                {
                    path: '',
                    name: 'clients.show',
                    component: ClientInformation,
                    props: true,
                },
                {
                    path: 'contacts',
                    name: 'clients.contacts',
                    component: ClientContacts,
                    props: true,
                },
                {
                    path: 'projects',
                    name: 'clients.projects',
                    component: ClientProjects,
                    props: true,
                },
                {
                    path: 'danger-zone',
                    name: 'clients.danger-zone',
                    component: ClientDangerZone,
                    props: true,
                },
            ],
        },

        {
            path: '/clients/:id/edit',
            name: 'clients.edit',
            component: ClientInformation,
            props: true,
        },

        {
            path: '/projects',
            name: 'projects.index',
            component: ProjectsIndex,
        },

        {
            path: '/projects/create',
            name: 'projects.create',
            component: ProjectCreate,
        },

        {
            path: '/projects/:id',
            component: ProjectLayout,
            props: true,
            children: [
                {
                    path: '',
                    name: 'projects.show',
                    component: ProjectInformation,
                    props: true,
                },
                {
                    path: 'edit',
                    name: 'projects.edit',
                    component: ProjectInformation,
                    props: true,
                },
                {
                    path: 'people',
                    name: 'projects.people',
                    component: ProjectPeople,
                    props: true,
                },
                {
                    path: 'tickets',
                    name: 'projects.tickets',
                    component: ProjectTickets,
                    props: true,
                },
                {
                    path: 'files',
                    name: 'projects.files',
                    component: ProjectFiles,
                    props: true,
                },
                {
                    path: 'billing',
                    name: 'projects.billing',
                    component: ProjectBilling,
                    props: true,
                },
                {
                    path: 'saas',
                    name: 'projects.saas',
                    component: SaasDetail,
                    props: true,
                },
                {
                    path: 'saas/customers/:companyId',
                    name: 'projects.saas.customer',
                    component: SaasCustomerDetail,
                    props: true,
                },
                {
                    path: 'portfolio',
                    name: 'portfolio.edit',
                    component: PortfolioDetail,
                    props: true,
                },
                {
                    path: 'danger-zone',
                    name: 'projects.danger-zone',
                    component: ProjectDangerZone,
                    props: true,
                },
            ],
        },

        {
            path: '/service-products',
            name: 'service-products.index',
            component: ServiceProductsIndex,
        },

        {
            path: '/service-products/create',
            name: 'service-products.create',
            component: ServiceProductDetail,
            props: {
                create: true,
            },
        },

        {
            path: '/service-products/:id',
            name: 'service-products.show',
            component: ServiceProductDetail,
            props: true,
        },

        {
            path: '/service-products/:id/edit',
            name: 'service-products.edit',
            component: ServiceProductDetail,
            props: true,
        },

        {
            path: '/coworkers',
            name: 'coworkers.index',
            component: CoworkersIndex,
        },

        {
            path: '/coworkers/create',
            name: 'coworkers.create',
            component: CoworkerDetail,
        },

        {
            path: '/coworkers/:id',
            name: 'coworkers.show',
            component: CoworkerDetail,
            props: true,
        },

        {
            path: '/coworkers/:id/edit',
            name: 'coworkers.edit',
            component: CoworkerDetail,
            props: true,
        },

        {
            path: '/internal-storage',
            name: 'internal-storage.index',
            component: InternalStorage,
        },

        {
            path: '/saas',
            name: 'saas.projects.index',
            component: SaasIndex,
        },

        {
            path: '/saas/projects/:id',
            name: 'saas.projects.show',
            component: SaasDetail,
            props: true,
        },

        {
            path: '/saas/projects/:id/customers/:companyId',
            name: 'saas.projects.customer',
            component: SaasCustomerDetail,
            props: true,
        },

        {
            path: '/portfolio',
            name: 'portfolio.index',
            component: PortfolioIndex,
        },

    ],

    scrollBehavior: () => ({
        top: 0,
    }),

})

export default router