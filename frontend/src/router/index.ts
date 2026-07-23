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
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (auth.isAuthenticated && ['login', 'register'].includes(String(to.name))) {
    return { name: 'home' }
  }
})

export default router
