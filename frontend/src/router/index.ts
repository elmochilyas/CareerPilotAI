import { createRouter, createWebHistory } from 'vue-router'
import DefaultLayout from '@/app/layouts/DefaultLayout.vue'
import GuestLayout from '@/app/layouts/guest/GuestLayout.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      component: GuestLayout,
      children: [
        {
          path: '',
          name: 'login',
          component: () => import('@/features/auth/pages/LoginPage.vue'),
        },
      ],
    },
    {
      path: '/register',
      component: GuestLayout,
      children: [
        {
          path: '',
          name: 'register',
          component: () => import('@/features/auth/pages/RegisterPage.vue'),
        },
      ],
    },
    {
      path: '/forgot-password',
      component: GuestLayout,
      children: [
        {
          path: '',
          name: 'forgot-password',
          component: () => import('@/features/auth/pages/ForgotPasswordPage.vue'),
        },
      ],
    },
    {
      path: '/reset-password',
      component: GuestLayout,
      children: [
        {
          path: '',
          name: 'reset-password',
          component: () => import('@/features/auth/pages/ResetPasswordPage.vue'),
        },
      ],
    },
    {
      path: '/email/verify',
      component: GuestLayout,
      children: [
        {
          path: '',
          name: 'email-verify',
          component: () => import('@/features/auth/pages/EmailVerificationPage.vue'),
        },
      ],
    },
    {
      path: '/',
      component: DefaultLayout,
      children: [
        {
          path: '',
          name: 'home',
          meta: { requiresAuth: true },
          component: () => import('@/features/home/pages/HomePage.vue'),
        },
        {
          path: 'profile',
          name: 'profile',
          meta: { requiresAuth: true },
          component: () => import('@/features/profile/pages/ProfilePage.vue'),
        },
        {
          path: 'cv',
          name: 'cv-ingestion',
          meta: { requiresAuth: true },
          component: () => import('@/features/cv-ingestion/pages/CvIngestionPage.vue'),
        },
        {
          path: 'opportunities',
          name: 'opportunities',
          meta: { requiresAuth: true },
          component: () => import('@/features/opportunities/pages/OpportunitiesListPage.vue'),
        },
        {
          path: 'opportunities/import',
          name: 'opportunities-import',
          meta: { requiresAuth: true },
          component: () => import('@/features/opportunities/pages/ImportJobPage.vue'),
        },
        {
          path: 'opportunities/ingestions/:id',
          name: 'opportunities-processing',
          meta: { requiresAuth: true },
          component: () => import('@/features/opportunities/pages/ProcessingPage.vue'),
        },
        {
          path: 'opportunities/ingestions/:id/review',
          name: 'opportunities-review',
          meta: { requiresAuth: true },
          component: () => import('@/features/opportunities/pages/ReviewPage.vue'),
        },
        {
          path: 'opportunities/:id',
          name: 'opportunities-detail',
          meta: { requiresAuth: true },
          component: () => import('@/features/opportunities/pages/OpportunityDetailPage.vue'),
        },
        {
          path: 'opportunities/:id/match',
          name: 'opportunities-match',
          meta: { requiresAuth: true },
          component: () => import('@/features/matching/pages/MatchBriefPage.vue'),
        },
        {
          path: 'opportunities/:id/match/clarifications',
          name: 'opportunities-match-clarifications',
          meta: { requiresAuth: true },
          component: () => import('@/features/clarification/pages/ClarificationPage.vue'),
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.initialize()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    const redirect = to.fullPath.replace(/^\/{2,}/, '/')
    return { name: 'login', query: { redirect } }
  }

  if (auth.isAuthenticated && ['login', 'register'].includes(String(to.name))) {
    return { name: 'home' }
  }
})

export default router
