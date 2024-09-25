<template>
  <component
    :is="dynamicComponent"
    v-model="completion_value"
    :completion="completion"
  />
</template>

<script setup>
import { computed } from 'vue';
import course_completed from './conditions/course_completed.vue'
import manual from './conditions/manual_check.vue'
import catquiz from './conditions/catQuiz.vue'
import modquiz from './conditions/modQuiz.vue'

const props = defineProps({
  completion: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits([
  'changevalues'
]);

const completion_value = computed({
  get() {
    return props.completion?.value;
  },
  set(newValue) {
    emit('changevalues', newValue);
  },
});


const dynamicComponent = computed(() => {
  switch (getInputLabel()) {
    case 'course_completed':
      return course_completed;
    case 'manual':
      return manual;
    case 'catquiz':
      return catquiz;
    case 'modquiz':
      return modquiz;
    default:
      return null;
  }
});

const getInputLabel = () => {
  // Map completion labels to input components
  const labelToComponent = {
    course_completed: 'course_completed',
    manual: 'manual',
    catquiz: 'catquiz',
    modquiz: 'modquiz',
  };
  return labelToComponent[props.completion.label] || 'manual';
};

</script>
