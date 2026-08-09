<script setup lang="ts">
import { RouterView, RouterLink } from 'vue-router'
import authBg from '@/assets/images/auth-bg.jpg'
</script>

<template>
  <div class="auth-bg">
    <img :src="authBg" alt="" class="auth-bg__img" loading="eager" decoding="async" />
    <div class="auth-bg__overlay" />

    <div class="auth-bg__content">
      <RouterLink to="/" class="auth-bg__logo" aria-label="CareerPilot home">
        <span
          class="auth-bg__logo-icon flex size-9 items-center justify-center rounded-xl bg-[var(--surface-primary)] text-sm font-bold text-[var(--text-primary)]"
        >
          CP
        </span>
        <span class="text-xl font-bold tracking-tight text-white"> CareerPilot </span>
      </RouterLink>

      <div class="auth-bg__card ds-animate-fade-in">
        <RouterView />
      </div>
    </div>
  </div>
</template>

<style scoped>
.auth-bg {
  position: fixed;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  background-color: var(--color-neutral-900);
}

.auth-bg__img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.auth-bg__overlay {
  position: absolute;
  inset: 0;
  background: rgba(16, 24, 40, 0.2);
}

.auth-bg__content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 26rem;
  padding: 2rem;
  max-height: 100vh;
  max-height: 100dvh;
}

.auth-bg__logo {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  text-decoration: none;
  flex-shrink: 0;
}

.auth-bg__logo-icon {
  box-shadow: var(--shadow-neo-raised);
  border: none;
}

.auth-bg__card {
  border-radius: 1rem;
  background-color: #ffffff;
  padding: 1.75rem;
  box-shadow: var(--shadow-neo-raised-lg);
  width: 100%;
  border: none;
}

@media (min-width: 768px) {
  .auth-bg__content {
    max-width: 38rem;
    padding: 2rem 10rem 2rem 3rem;
  }

  .auth-bg__card {
    padding: 2rem;
  }
}

@media (max-width: 767px) {
  .auth-bg {
    justify-content: center;
  }

  .auth-bg__content {
    max-width: 26rem;
    padding: 1.5rem 1rem;
  }

  .auth-bg__logo {
    justify-content: center;
  }

  .auth-bg__card {
    padding: 1.5rem;
    border-radius: var(--radius-lg);
  }
}
</style>
