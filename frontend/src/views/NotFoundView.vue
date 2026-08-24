<template>
  <div class="mx-auto max-w-md py-16 text-center">
    <p class="text-5xl font-bold text-brand-700">404</p>
    <h1 class="mt-3 text-xl font-bold text-slate-900">Página no encontrada</h1>
    <p class="mt-2 text-sm text-slate-600">
      La dirección solicitada no existe o el enlace de verificación es incorrecto.
    </p>
    <RouterLink :to="{ name: 'public.search' }" class="btn-primary mt-6">
      Ir a la consulta pública
    </RouterLink>
  </div>
</template>
