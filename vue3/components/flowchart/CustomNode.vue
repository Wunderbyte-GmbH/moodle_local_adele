<script setup>
import { defineProps } from 'vue';
import { Position } from '@vue-flow/core'

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
})


</script>

<template>
     <div class="custom-node">
        <div>Select a color</div>
        <button class="btn btn-secondary" >Pretest</button>
        <button class="btn btn-secondary" >Pretest</button>
        <button class="btn btn-secondary" >Pretest</button>

    </div>
</template>


<style scoped>
.custom-node {
  background-color: white;
  padding: 10px;
  border: 1px solid #ccc;
}
</style>
