<script setup>
  // Import needed libraries
  import { onMounted, onUnmounted, ref } from 'vue';

  // Load Store
  const showFeedbackarea = ref(false);

  const props = defineProps({
    data: {
      type: Object,
      required: true,
    },
  });

  const toggleFeedbackarea = () => {
    showFeedbackarea.value = !showFeedbackarea.value;
  };

  // Function to close the feedback area if clicking outside
  const closeOnOutsideClick = (event) => {
    if (!event.target.closest('.' + props.data.node_id + '_user_info_listener')) {
      showFeedbackarea.value = false;
    }
  };

  // Setup and cleanup event listeners
  onMounted(() => {
    document.addEventListener('click', closeOnOutsideClick);
  });

  onUnmounted(() => {
    document.removeEventListener('click', closeOnOutsideClick);
  });

</script>

<template>
  <div
    v-if="data.completion.feedback && data.completion.feedback.completion.inbetween"
    class="card-container"
    :class="{ 'card-hover': showCard, [data.node_id + '_user_info_listener']: true}"
    @click="toggleFeedbackarea"
  >
    <div>
      <i class="fa fa-comment" />
    </div>
    <transition name="fade">
      <div v-if="showFeedbackarea">
        <div
          v-for="feedback_after in data.completion.feedback.completion.inbetween"
          :key="feedback_after"
        >
          {{ feedback_after }}
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.card-container {
  cursor: pointer;
  justify-content: center; /* Center children horizontally */
  align-items: center; /* Center children vertically */
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  font-weight: bold; /* Make the text bold */
  text-align: center;
}

.fa-comment {
  color: #333;
  font-size: 20px; /* Adjust the size as needed */
}

.card-container:hover {
  background-color: rgb(213, 207, 207); /* Change background color on hover */
}

.feedback-container {
  width: 100%; /* Ensures the container fills the width of its parent */
  display: flex;
  flex-direction: column;
  align-items: center;
}

textarea {
  width: 100%;
  padding: 10px;
  border-radius: 5px;
  border: 1px solid #ced4da;
  resize: none;
  font-family: inherit;
  font-size: 1rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  transition: border-color 0.2s, box-shadow 0.2s;
}

textarea:focus {
  border-color: #80bdff; /* Highlight color when focused */
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25); /* Bootstrap-like focus shadow */
  outline: none; /* Removes the default focus outline */
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
