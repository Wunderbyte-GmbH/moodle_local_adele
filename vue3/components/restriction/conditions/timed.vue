<template>
  <div class="form-check">
    {{ restriction.description }}
    <input type="date" :value="data" @input="updateSelectedDate" />
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps(['modelValue', 'restriction']);
const data = ref(null);
const emit = defineEmits()

const updateSelectedDate = (event) => {
  data.value = event.target.value;
  emit('update:modelValue', data.value);
};

// Initialize the input with the modelValue
onMounted(() => {
  data.value = props.modelValue;
});

// Watch for changes in modelValue
watch(() => props.modelValue, (newValue) => {
  data.value = newValue;
}, { deep: true } );
</script>