<template>
  <div v-for="output_item in output_items">
    <component :is="getOutputLabel(output_item)" v-model="completion.value" :completion="completion"/>
  </div>
</template>

<script setup>
import manual_output from './conditions_output/manual_output.vue'

const props = defineProps(['completion']);
const output_items = ['manual'];

const dynamicComponent = (output_item) => {
  switch (output_item) {
    case 'manual':
      return manual_output;
    default:
      return null;
  }
};

const getOutputLabel = (output_item) => {
  if (props.completion) {
    return dynamicComponent(output_item)
  }else{
    return null;
  } 
};

</script>
