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
      {{ store.state.strings.restriction_access_manually }}
    </label>
  </div>
</template>

<script setup>
import { useStore } from 'vuex'

const store = useStore()

defineProps({
  modelValue: {
    type: Boolean,
    default: null,
  }, 
  data: {
    type: Object,
    required: true,
  }
});

</script>