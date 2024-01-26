<template>
  <div class="progress-container">
    <div
      class="progress-bar"
      :class="{ 'bg-success': props.progress > 0, 'no-progress': props.progress === 0 }"
      role="progressbar"
      :style="{ width: props.progress + '%' }"
      aria-valuenow="{{ props.progress }}"
      aria-valuemin="0"
      aria-valuemax="100"
    >
      {{ props.progress > 0 ? props.progress + '%' : 'No Progress' }}
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  progress: {
    type: Number,
    required: true,
  },
});
</script>

<style scoped>
.progress-container {
  height: 20px;
  overflow: hidden;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
}

.progress-bar {
  text-align: center;
  line-height: 20px;
  color: #fff;
  border-radius: 10px;
  transition: width 0.3s ease, background-color 0.3s ease; /* Add a smooth transition effect */
}

/* Customize the background color when progress is zero */
.progress-bar.bg-success {
  background-color: #28a745;
}

/* Style for 'No Progress' text when progress is zero */
.no-progress {
  color: #000000 !important; /* Use !important to ensure the color is applied */
}

.no-progress::before {
  content: 'No Progress';
  display: inline-block;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}
</style>
