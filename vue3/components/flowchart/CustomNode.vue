<script setup>
import { Handle, Position } from '@vue-flow/core'
import { defineProps, computed  } from 'vue';
import { useStore } from 'vuex';

const store = useStore();
const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const setNodeModal = () => {
  store.state.node = props.data
  console.log(props.data)
};

const sourceHandleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))
const targetHandleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

</script>

<template>

  <div class="custom-node text-center" >
    <div class="mb-2"><b>{{ store.state.strings.node_coursefullname }}</b> {{ data.fullname }}</div>
    <div class="mb-2"><b>{{ store.state.strings.node_courseshortname }}</b> {{ data.shortname }}</div>
    <button type="button" class="btn btn-primary" @click="setNodeModal" data-toggle="modal" data-target="#exampleModal">
      Launch demo modal
    </button>

  </div>
  <Handle id="a" type="source" :position="Position.Right" :style="sourceHandleStyle" />
  <Handle id="b" type="target" :position="Position.Left" :style="targetHandleStyle" />
</template>

<style scoped>
.custom-node {
  background-color: white;
  padding: 10px;
  border: 1px solid #ccc;
}

</style>