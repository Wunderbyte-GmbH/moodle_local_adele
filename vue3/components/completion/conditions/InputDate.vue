<template>
  <div class="form-check">
    <label :for="completion.label">{{ completion.description }}</label>
    <input
      :id="completion.label"
      class="form-control rounded"
      type="date"
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
    />
  </div>
</template>

<script setup>

const props = defineProps(['modelValue', 'completion']);

</script>
