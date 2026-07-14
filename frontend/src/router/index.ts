import { createRouter, createWebHistory } from 'vue-router'
import DefaultLayout from '@/app/layouts/DefaultLayout.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: DefaultLayout,
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/features/home/pages/HomePage.vue'),
        },
      ],
    },
  ],
})

export default router
