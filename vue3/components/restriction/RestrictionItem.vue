<template>
  <component
    :is="dynamicComponent"
    v-model="restriction_value"
    :restriction="restriction"
    :learningpath="learningpath"
  />
</template>

<script setup>
import { computed } from 'vue';
import manual from '../restriction/conditions/manual_check.vue'
import manual_output from '../restriction/conditions_output/manual_output.vue'
import timed from '../restriction/conditions/timed_dates.vue'
import specific_course from '../restriction/conditions/specific_course.vue'
import parent_node_completed from '../restriction/conditions/parent_node_completed.vue'
import parent_courses from '../restriction/conditions/parent_courses.vue'
import timed_duration from '../restriction/conditions/timed_duration.vue'

const props = defineProps({
  restriction: {
    type: Object,
    required: true,
  },
  learningpath: {
    type: Object,
    required: true,
  }
});

const emit = defineEmits([
  'changevalues'
]);

const restriction_value = computed({
  get() {
    return props.restriction?.value;
  },
  set(newValue) {
    emit('changevalues', newValue);
  },
});

const dynamicComponent = computed(() => {
  switch (getInputLabel()) {
    case 'manual':
      return manual;
    case 'timed':
      return timed;
    case 'manual_output':
      return manual_output;
    case 'specific_course':
      return specific_course;
    case 'parent_courses':
      return parent_courses;
    case 'parent_node_completed':
      return parent_node_completed;
    case 'timed_duration':
      return timed_duration;
    default:
      return null;
  }
});

const getInputLabel = () => {
  // Map completion labels to input components
  const labelToComponent = {
    manual: 'manual',
    timed: 'timed',
    specific_course: 'specific_course',
    parent_courses: 'parent_courses',
    parent_node_completed: 'parent_node_completed',
    timed_duration: 'timed_duration',
  };
  return labelToComponent[props.restriction.label] || 'manual';
};

</script>
