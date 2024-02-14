<template>
  <div class="form-check">
    {{ completion.description }}
  </div>
</template>

<script setup>

  defineProps({
    modelValue: {
      type: Object,
      default: null,
    },
    completion: {
      type: Object,
      default: null,
    },
  });
</script>