import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from './pages/Dashboard.vue'
import ClientsIndex from './pages/clients/ClientsIndex.vue'
import ClientForm from './pages/clients/ClientForm.vue'
import ContactForm from './pages/contacts/ContactForm.vue'
import ProjectForm from './pages/projects/ProjectForm.vue'
import ProjectWorkspace from './pages/projects/ProjectWorkspace.vue'
import ProjectsIndex from './pages/projects/ProjectsIndex.vue'
import ServiceProductsIndex from './pages/service-products/ServiceProductsIndex.vue'
import ServiceProductDetail from './pages/service-products/ServiceProductDetail.vue'
import PortfolioIndex from './pages/portfolio/PortfolioIndex.vue'
import PortfolioEditor from './pages/portfolio/PortfolioEditor.vue'
import CoworkersIndex from './pages/coworkers/CoworkersIndex.vue'
import InternalStorageIndex from './pages/internal-storage/InternalStorageIndex.vue'

const router = createRouter({
    history: createWebHistory('/admin/client-portal/'),
    routes: [
        { path: '/', name: 'dashboard', component: Dashboard },
        { path: '/clients', name: 'clients.index', component: ClientsIndex },
        { path: '/clients/create', name: 'clients.create', component: ClientForm },
        { path: '/clients/:id', name: 'clients.show', component: ClientForm, props: true },
        { path: '/clients/:id/edit', name: 'clients.edit', component: ClientForm, props: true },
        { path: '/clients/:companyId/contacts/create', name: 'contacts.create', component: ContactForm, props: true },
        { path: '/clients/:companyId/contacts/:id/edit', name: 'contacts.edit', component: ContactForm, props: true },
        { path: '/projects', name: 'projects.index', component: ProjectsIndex },
        { path: '/projects/create', name: 'projects.create', component: ProjectForm },
        { path: '/projects/:id', name: 'projects.show', component: ProjectWorkspace, props: true },
        { path: '/projects/:id/edit', name: 'projects.edit', component: ProjectForm, props: true },
        { path: '/service-products', name: 'service-products.index', component: ServiceProductsIndex },
        { path: '/service-products/create', name: 'service-products.create', component: ServiceProductDetail, props: { create: true } },
        { path: '/service-products/:id', name: 'service-products.show', component: ServiceProductDetail, props: true },
        { path: '/service-products/:id/edit', name: 'service-products.edit', component: ServiceProductDetail, props: true },
        { path: '/coworkers', name: 'coworkers.index', component: CoworkersIndex },
        { path: '/internal-storage', name: 'internal-storage.index', component: InternalStorageIndex },
        { path: '/portfolio', name: 'portfolio.index', component: PortfolioIndex },
        { path: '/projects/:id/portfolio', name: 'portfolio.edit', component: PortfolioEditor, props: true },
    ],
    scrollBehavior: () => ({ top: 0 }),
})

export default router