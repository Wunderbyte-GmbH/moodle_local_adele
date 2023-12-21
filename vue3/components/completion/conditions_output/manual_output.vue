<template>
  <div class="form-check">
    <input
      :id="completion.node_id"
      class="form-check-input"
      type="checkbox"
      :checked="modelValue"
      @change="$emit('update:modelValue', $event.target.checked)"
    />
    <label :for="completion.node_id">Finish course manual</label>
  </div>
</template>

<script setup>

const props = defineProps(['modelValue', 'completion']);

</script>