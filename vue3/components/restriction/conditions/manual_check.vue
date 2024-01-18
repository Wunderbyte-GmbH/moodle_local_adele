<template>
  <div class="form-check">
    {{ restriction.description }}
  </div>
</template>

<script setup>

defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  restriction: {
    type: Object,
    required: true,
  }});

</script>