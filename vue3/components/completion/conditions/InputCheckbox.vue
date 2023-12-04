<template>
  <div class="form-check">
    <input
      :id="completion.label"
      class="form-check-input"
      type="checkbox"
      :checked="modelValue"
      @change="$emit('update:modelValue', $event.target.checked)"
    />
    <label :for="completion.label">{{ completion.description }}</label>
  </div>
</template>

<script setup>

const props = defineProps(['modelValue', 'completion']);

</script>