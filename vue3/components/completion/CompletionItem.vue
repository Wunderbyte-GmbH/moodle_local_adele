<template>
  <component :is="dynamicComponent" v-model="completion.value" :completion="completion"/>
</template>

<script setup>
import { computed } from 'vue';
import course_completed from './conditions/course_completed.vue'
import manual from '../completion/conditions/manual.vue'
import catquiz from './conditions/catquiz.vue'
import manual_output from './conditions_output/manual_output.vue'

const props = defineProps(['completion']);

const dynamicComponent = computed(() => {
  switch (getInputLabel()) {
    case 'course_completed':
      return course_completed;
    case 'manual':
      return manual;
    case 'manual_output':
      return manual_output;
    case 'catquiz':
      return catquiz;
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
  };
  return labelToComponent[props.completion.label] || 'manual';
};

</script>
