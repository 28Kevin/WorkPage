<script setup>
import { computed, useId } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  error: { type: [String, Array], default: null },
  hint: { type: String, default: null },
  required: { type: Boolean, default: false },
})

const id = useId()
const message = computed(() => (Array.isArray(props.error) ? props.error[0] : props.error))
</script>

<template>
  <div>
    <label :for="id" class="field-label">
      {{ label }}
      <span v-if="required" class="text-red-500" aria-hidden="true">*</span>
    </label>

    <slot :id="id" :has-error="Boolean(message)" />

    <p v-if="message" class="field-error">{{ message }}</p>
    <p v-else-if="hint" class="field-hint">{{ hint }}</p>
  </div>
</template>
