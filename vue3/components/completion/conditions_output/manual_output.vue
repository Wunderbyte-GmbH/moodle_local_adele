<template>
  <div class="form-check">
    <input
      :id="data.node_id"
      class="form-check-input"
      type="checkbox"
      :checked="modelValue"
      @change="$emit('update:modelValue', $event.target.checked)"
    >
    <label :for="data.node_id">
      {{ store.state.strings.conditions_finish_course }}
    </label>
  </div>
</template>

<script setup>

import { useStore } from 'vuex';
const store = useStore();

defineProps({
  modelValue: {
    type: Boolean,
    default: null,
  }, 
  data: {
    type: Object,
    default: null,
  }});
</script>