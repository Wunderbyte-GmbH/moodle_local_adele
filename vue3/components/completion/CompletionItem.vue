<template>
  <component :is="dynamicComponent" v-model="completion.value" :completion="completion"/>
</template>

<script setup>
import { computed } from 'vue';
import InputCheckbox from './conditions/InputCheckbox.vue';
import InputDate from './conditions/InputDate.vue';
import course_completed from './conditions/course_completed.vue'
import ManualOutput from '../completion/conditions_output/ManualOutput.vue'

const props = defineProps(['completion']);

const dynamicComponent = computed(() => {
  switch (getInputType()) {
    case 'InputCheckbox':
      return InputCheckbox;
    case 'InputDate':
      return InputDate;
    case 'course_completed':
      return course_completed;
    case 'Manual':
      return ManualOutput;
    default:
      return null;
  }
});

const getInputType = () => {
  // Map completion types to input components
  const typeToComponent = {
    checkbox: 'InputCheckbox',
    date: 'InputDate',
    course_completed: 'course_completed',
  };
  if(props.completion.manual){
    return 'Manual';
  }
  return typeToComponent[props.completion.type] || 'InfoText';
};

</script>
