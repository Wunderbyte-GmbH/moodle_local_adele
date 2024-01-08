<template>
  <component :is="dynamicComponent" v-model="restriction.value" :restriction="restriction"/>
</template>

<script setup>
import { computed } from 'vue';
import manual from '../restriction/conditions/manual.vue'
import timed from '../restriction/conditions/timed.vue'

const props = defineProps(['restriction']);

const dynamicComponent = computed(() => {
  switch (getInputLabel()) {
    case 'manual':
      return manual;
    case 'timed':
      return timed;
    default:
      return null;
  }
});

const getInputLabel = () => {
  // Map completion labels to input components
  const labelToComponent = {
    manual: 'manual',
    timed: 'timed',
  };
  return labelToComponent[props.restriction.label] || 'manual';
};

</script>
