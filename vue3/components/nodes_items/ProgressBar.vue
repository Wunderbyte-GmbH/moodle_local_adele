<template>
  <p>{{props.status}}</p>
  <div class="progress-container">
    <div
      class="progress-bar"

      role="progressbar"
       :style="[progressStyle, { width: props.progress + '%' }]"
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
import { computed } from 'vue';
import { useStore } from 'vuex';
const store = useStore();

const props = defineProps({
  progress: {
    type: Number,
    required: true,
  },
  status: {
    type: String,
    required: false,
  }
});


const progressStyle = computed(() => {
  let color;
  switch (props.statusMessage) {
    case '0':
      color = 'red'; // White
      break;
    case 'a1':
      color = 'red'; // Gold
      break;
    case 'a2':
      color = 'red'; // Orange
      break;
    case 'b':
      color = 'red'; // Green
      break;
    case 'c':
      color = 'red'; // Blue
      break;
    case 'd':
      color = 'red'; // Indigo
      break;
    case 'e':
      color = 'red'; // Violet
      break;
    case 'f':
      color = 'red'; // Red
      break;
    default:
      color = 'red'; // Default Gray
      break;
  }
  return { backgroundColor: color };
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
