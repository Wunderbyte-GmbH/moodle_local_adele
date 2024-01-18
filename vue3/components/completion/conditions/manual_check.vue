<template>
  <div class="form-check">
    {{ completion.description }}
  </div>
</template>

<script setup>
  import { defineProps } from 'vue';

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