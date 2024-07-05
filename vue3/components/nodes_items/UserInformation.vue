
<script setup>
  // Import needed libraries
  import { onMounted, onUnmounted, ref } from 'vue';
  import UserFeedbackBlock from './UserFeedbackBlock.vue';
  import { useStore } from 'vuex';

  // Load Store
  const showFeedbackarea = ref(false);
  const store = useStore()

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
        <div v-if="data.completion.feedback.status" class="status-text">
          <i class="fa fa-info-circle"></i>
          <span>{{ store.state.strings['node_access_' + data.completion.feedback.status] }}</span>
        </div>
        <UserFeedbackBlock
          :data="data.completion.feedback.restriction.before"
          title="restriction_before"
        />
        <UserFeedbackBlock
          :data="data.completion.feedback.completion.before"
          title="completion_before"
        />
        <UserFeedbackBlock
          :data="data.completion.feedback.completion.inbetween"
          title="completion_inbetween"
        />
        <UserFeedbackBlock
          :data="data.completion.feedback.completion.after"
          title="completion_after"
        />
        <UserFeedbackBlock
          :data="data.completion.feedback.completion.higher"
          title="completion_higher"
        />
      </div>
    </transition>
  </div>
</template>

<style scoped>
.feedback-list {
  list-style-type: disc;
  padding-left: 20px;
}

.feedback-item {
  position: relative;
}

.feedback-text {
  text-align: left;
  display: inline-block;
  width: 100%;
}

.or-text {
  display: block;
  font-style: italic;
  text-align: center;
  margin-top: 5px;
  margin-bottom: 5px;
}

.feedback-title {
  font-weight: bold;
  padding: 10px 0;
}

.card-container {
  cursor: pointer;
  justify-content: center;
  align-items: center;
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  text-align: center;
}

.fa-comment {
  color: #333;
  font-size: 20px;
}

.card-container:hover {
  background-color: rgb(213, 207, 207);
}

.feedback-container {
  width: 100%;
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
  border-color: #80bdff;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
  outline: none;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
.status-text {
  font-weight: bold;
  align-items: center;
}

.status-text .fa-info-circle {
  margin-right: 5px;
}
</style>