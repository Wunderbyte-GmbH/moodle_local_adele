<template>
  <div class="form-check">
    <div class="input-group mb-3">
      <span class="input-group-text rounded-end-0">{{ descriptions.start }}</span>
      <input
        type="date"
        class="form-control"
        :value="data.start"
        @input="updateSelectedDate('start', $event)"
      >
    </div>
    <div class="input-group mb-3">
      <span class="input-group-text rounded-end-0">{{ descriptions.end }}</span>
      <input
        type="date"
        class="form-control"
        :value="data.end"
        @input="updateSelectedDate('end', $event)"
      >
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  }, 
  restriction: {
    type: Object,
    required: true,
  },
  });
const data = ref({
  start: null,
  end: null,
});
const descriptions = ref({
  start: null,
  end: null,
});
const emit = defineEmits(['update:modelValue'])

const updateSelectedDate = (type, event) => {
  data.value[type] = event.target.value;
  emit('update:modelValue', data.value);
};

// Initialize the input with the modelValue
onMounted(() => {
  if (props.modelValue != null) {
    data.value = props.modelValue;
  } 
  let tmp_descriptions = props.restriction.description.split(';')
  descriptions.value.start = tmp_descriptions[0]
  descriptions.value.end = tmp_descriptions[1]
});

// Watch for changes in modelValue
watch(() => props.modelValue, (newValue) => {
  data.value = newValue;
}, { deep: true } );
</script>

<style scoped>
.rounded-end-0 {
  border-top-right-radius: 0;
  border-bottom-right-radius: 0;
}
</style>