<template>
  <div class="progress-container">
    <div
      class="progress-bar"
      :class="{ 'bg-success': props.progress > 0 }"
      role="progressbar"
      :style="{ width: props.progress + '%' }"
      aria-valuenow="{{ props.progress }}"
      aria-valuemin="0"
      aria-valuemax="100"
    />
    <div 
      class="progress-label" 
      :style="props.progress > 0 ? 'font-size: 0.8em' : 'font-size: 0.55em'"
    > 
      {{ props.progress > 0 ? props.progress + '%' : store.state.strings.nodes_items_no_progress }}
    </div> 
  </div>
</template>

<script setup>
import { useStore } from 'vuex';
const store = useStore();

const props = defineProps({
  progress: {
    type: Number,
    required: true,
  },
});
</script>

<style scoped>
.progress-container {
  width: 80%;
  height: 20px;
  overflow: hidden;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
  position: relative;
}

.progress-bar {
  text-align: center;
  line-height: 20px;
  height: 100%;
  color: #fff;
  border-radius: 10px;
  transition: width 0.3s ease, background-color 0.3s ease; /* Add a smooth transition effect */
}

/* Customize the background color when progress is zero */
.progress-bar.bg-success {
  background-color: #28a745;
}

.progress-label {
  position: absolute;
  line-height: 20px;
  height: 100%;
  position: absolute;
  top: 0;
  width: 100%;
  text-align: center;
}
</style>
