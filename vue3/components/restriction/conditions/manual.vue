<template>
  <div class="form-check">
    {{ restriction.description }}
  </div>
</template>

<script setup>

const props = defineProps(['modelValue', 'restriction']);

</script>