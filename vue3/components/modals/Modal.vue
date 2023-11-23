<template>
    <div class="modal-content">
      You want to edit the Node
      <button @click="closeModal">Close Modal</button>
    </div>
  </template>
  
  <script>
  export default {
    props: {
      showModal: Boolean,
    },
    methods: {
      closeModal() {
        this.$emit('close');
      },
    },
  };
  </script>