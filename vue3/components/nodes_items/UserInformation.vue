
<script setup>
  // Import needed libraries
  import { ref } from 'vue';
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
    mobile: {
      type: Boolean,
      default: false,
    },
  });

  const toggleFeedbackarea = () => {
    showFeedbackarea.value = !showFeedbackarea.value;
  };

</script>

<template>
  <div
    class="card-container"
    :class="{ [data.node_id + '_user_info_listener']: true}"
    @click.stop
  >
    <div
      class="toggle-button"
      @click.stop="toggleFeedbackarea"
    >
      <i
        class="fa fa-comment"
        :class="{'fa-comment-mobile' : mobile}"
      />
    </div>
    <transition name="fade">
      <div v-if="showFeedbackarea"
        class="selectable"
        @mousedown.stop
        @mousemove.stop
        @mouseup.stop>
        <div v-if="data.completion && data.completion.feedback.status" class="status-text">
          <i class="fa fa-info-circle"></i>
          <span>{{ store.state.strings['node_access_' + data.completion.feedback.status] }}</span>
        </div>
        <UserFeedbackBlock
          v-if="data.completion &&
            (data.completion.feedback.status == 'not_accessible' || data.completion.feedback.status == 'closed')"
          :data="data.completion.feedback.restriction.before"
          title="restriction_before"
        />
        <UserFeedbackBlock
          v-if="data.completion && data.completion.feedback.status == 'closed'"
          :data="data.completion.feedback.completion.before"
          title="completion_before"
        />
        <UserFeedbackBlock
          v-if="data.completion && data.completion.feedback.status != 'completed' &&
            data.completion.feedback.status != 'closed'"
          :data="data.completion.feedback.completion.inbetween"
          title="completion_inbetween"
        />
        <UserFeedbackBlock
          v-if="data.completion"
          :data="data.completion.feedback.completion.after"
          title="completion_after"
        />
        <UserFeedbackBlock
          v-if="data.completion"
          :data="data.completion.feedback.completion.higher"
          title="completion_higher"
        />
        <div
          v-if="!data.completion"
        >
          {{ store.state.strings.node_access_nothing_defined }}
        </div>
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
  justify-content: center;
  align-items: center;
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  text-align: center;
}

.toggle-button {
  cursor: pointer;
}

.fa-comment {
  color: #333;
  font-size: 20px;
}

.fa-comment-mobile {
  font-size: 50px !important;
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
.selectable {
  user-select: text;
  cursor: text;
}
</style>