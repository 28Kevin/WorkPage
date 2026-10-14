<script setup>
import { useId } from 'vue'
import { PAGE_SIZES } from '@/composables/useTableQuery'

defineProps({
  modelValue: { type: Number, required: true },
  /** Total de filas del listado, para situar al usuario. */
  total: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue'])

const id = useId()
</script>

<template>
  <div class="flex items-center gap-2 text-sm text-slate-600">
    <label :for="id">Mostrar</label>

    <select
      :id="id"
      class="rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:outline-none"
      :value="modelValue"
      @change="emit('update:modelValue', Number($event.target.value))"
    >
      <option v-for="size in PAGE_SIZES" :key="size" :value="size">{{ size }}</option>
    </select>

    <span>registros<template v-if="total !== null"> de {{ total }}</template></span>
  </div>
</template>
