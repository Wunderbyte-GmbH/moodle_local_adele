<script setup>
import { Handle, Position } from '@vue-flow/core'
import { defineProps, ref, computed  } from 'vue';
import Modal from '../modals/Modal.vue';
import { useStore } from 'vuex';

const store = useStore();
const isModalOpen = ref(false);

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const sourceHandleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))
const targetHandleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

const modalOpen = ref(false);

const editCourse = (context) => {
  isModalOpen.value = !isModalOpen.value;
  console.log(context);
};

const closeModal = () => {
  modalOpen.value = false;
};
</script>

<template>
  <div class="custom-node text-center" >
    <div class="mb-2"><b>{{ store.state.strings.node_coursefullname }}</b> {{ data.fullname }}</div>
    <div class="mb-2"><b>{{ store.state.strings.node_courseshortname }}</b> {{ data.shortname }}</div>
    <button class="btn btn-primary" @click="editCourse('Pretest')">Edit Course</button>
  </div>
  <Handle id="a" type="source" :position="Position.Right" :style="sourceHandleStyle" />
  <Handle id="b" type="target" :position="Position.Left" :style="targetHandleStyle" />

<teleport v-if="isModalOpen" to="#page-header">
  <Modal :showModal="isModalOpen" @close="closeModal">
  <!-- Modal content -->
  <h1>Hello there</h1>
  <p>Vue 3 is awesome!!!</p>
</Modal>
</teleport>

</template>

<style scoped>
.custom-node {
  background-color: white;
  padding: 10px;
  border: 1px solid #ccc;
}

</style>