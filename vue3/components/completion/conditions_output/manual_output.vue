<template>
  <div class="form-check">
    <input
      :id="data.node_id"
      class="form-check-input"
      type="checkbox"
      :checked="modelValue"
      @change="$emit('update:modelValue', $event.target.checked)"
    >
    <label :for="data.node_id">Finish course manually</label>
  </div>
</template>

<script setup>
import { defineProps } from 'vue';

defineProps({
  modelValue: {
    type: Array,
    default: null,
  }, 
  data: {
    type: Object,
    default: null,
  }});
</script>