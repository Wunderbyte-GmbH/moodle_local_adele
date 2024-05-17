<template>
  <div class="tooltip-container">
    <i class="ml-2 fa fa-exclamation-triangle" />
    <div class="tooltip-text">
      {{ store.state.strings.nodes_warning_time_restriction }}
    </div>
  </div>
</template>

<script setup>
  import { useStore } from 'vuex';
  const store = useStore();
</script>

<style scoped>
  .tooltip-container {
    position: relative;
    display: inline-block;
    cursor: pointer;
  }

  .tooltip-container .tooltip-text {
    visibility: hidden;
    width: 300px; /* Set a max-width for the tooltip */
    background-color: black;
    color: #fff;
    text-align: center;
    border-radius: 5px;
    padding: 10px 20px; /* Increase the padding to give more space on the sides */
    position: absolute;
    z-index: 1;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: opacity 0.6s ease-in-out, visibility 0.6s ease-in-out, transform 0.6s ease-in-out;
    white-space: normal; /* Ensure the text wraps */
    word-break: break-word; 
  }

  .tooltip-container:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
  }
</style>