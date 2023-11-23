<script setup>
import { defineProps, ref } from 'vue';
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