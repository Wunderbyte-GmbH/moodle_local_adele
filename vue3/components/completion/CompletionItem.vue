<template>
  <component :is="dynamicComponent" v-model="completion.value" :completion="completion"/>
</template>

<script setup>
import { computed } from 'vue';
import course_completed from './conditions/course_completed.vue'
import manual_output from '../completion/conditions_output/manual_output.vue'
import catquiz from './conditions/catquiz.vue'

const props = defineProps(['completion']);

const dynamicComponent = computed(() => {
  switch (getInputType()) {
    case 'course_completed':
      return course_completed;
    case 'manual':
      return manual_output;
    case 'catquiz':
      return catquiz;
    default:
      return null;
  }
});

const getInputType = () => {
  // Map completion types to input components
  const typeToComponent = {
    course_completed: 'course_completed',
    catquiz: 'catquiz',
  };
  if(props.completion.manual){
    return 'manual';
  }
  return typeToComponent[props.completion.type] || 'manual';
};

</script>
