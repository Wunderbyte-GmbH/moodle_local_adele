<template>
  TestinInput
  <div class="form-check">
    {{ completion.description }}
  </div>
</template>

<script setup>

const props = defineProps(['modelValue', 'completion']);

</script>