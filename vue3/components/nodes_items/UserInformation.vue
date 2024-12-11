<script setup>
// Import needed libraries
import { ref, watchEffect } from 'vue';
import UserFeedbackBlock from './UserFeedbackBlock.vue';
import { useStore } from 'vuex';

// Load Store
const showFeedbackarea = ref(false);
const feedbackStyle = ref({});
const store = useStore();

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
  if (!showFeedbackarea.value) {
    cardStyle.value.zIndex = 2;
  }
  handleFocus()
};

watchEffect(() => {
  if (showFeedbackarea.value) {
    feedbackStyle.value = {
      position: props.mobile ? 'relative' : 'absolute',
      top: '100%',
      left: '0',
      width: '100%',
      backgroundColor: '#EAEAEA',
      padding: '10px',
      borderBottomLeftRadius: '8px',
      borderBottomRightRadius: '8px',
      boxShadow: '0 2px 10px rgba(0, 0, 0, 0.1)',
    };
  } else {
    feedbackStyle.value = {};
  }
});

const cardStyle = ref({
  zIndex: props.mobile ? 1 : 2,
});
const handleFocus = () => {
  if (!props.mobile) {
    if (showFeedbackarea.value) {
      cardStyle.value.zIndex = 4;
    } else {
      cardStyle.value.zIndex = 2;
    }
  }
};

const handleBlur = () => {
  if (!props.mobile) {
    cardStyle.value.zIndex = 2;
  }
};
</script>

<template>
  <div class="card-container" tabindex="0" :style="cardStyle"
    :class="{ 'no-bottom-radius': showFeedbackarea, [data.node_id + '_user_info_listener']: true }" @click.stop
    @focus="handleFocus" @blur="handleBlur">
    <div class="toggle-button" :class="{ 'no-bottom-radius': showFeedbackarea }" @click.stop="toggleFeedbackarea">
      <i class="fa fa-comment" :class="{ 'fa-comment-mobile': mobile }" />
    </div>
    <transition name="fade">
      <div v-if="showFeedbackarea" :style="feedbackStyle" class="selectable" @mousedown.stop @mousemove.stop
        @mouseup.stop>
        <!-- Render status for feedback. -->
        <div
          v-if="data.completion && data.completion.feedback.status_restriction && data.completion.feedback.restriction.before_valid"
          class="status-text">
          <i class="fa fa-info-circle"></i>
          <span>{{ store.state.strings['node_access_restriction_' + data.completion.feedback.status_restriction]
            }}</span>
        </div>
        <div
          v-if="data.completion && data.completion.feedback.status_restriction === 'inbetween'"
          class="status-text">
          <i class="fa fa-info-circle"></i>
          <span>{{ store.state.strings['node_access_restriction_' + data.completion.feedback.status_restriction]
            }}</span>
        </div>
        <div
          v-if="data.completion && data.completion.feedback.status_restriction !== 'inbetween' && !data.completion.feedback.restriction.before_valid"
          class="status-text">
          <i class="fa fa-info-circle"></i>
          <span>{{ store.state.strings['node_access_restriction_after']
            }}</span>
        </div>
        <!-- Render before restriction feedback. -->
        <div v-if="data.completion &&
          data.completion.feedback.status_restriction == 'before'">
          <UserFeedbackBlock :data="Object.values(data.completion.feedback.restriction.before_active)"
            title="restriction_before" />
        </div>
        <div v-if="data.completion &&
          data.completion.feedback.status_restriction == 'before' && data.completion.feedback.restriction.before_timed">
         <span>{{ data.completion.feedback.restriction.before_timed }} </span>
        </div>
        <!-- Render between restriction feedback. -->
        <div v-if="data.completion && data.completion.feedback.restriction.inbetween &&
          data.completion.feedback.status_restriction == 'inbetween'">
          <UserFeedbackBlock :data="Object.values(data.completion.feedback.restriction.inbetween)"
            title="restriction_before" />
        </div>
        <!-- Render status for feedback. -->
        <div
          v-if="data.completion && data.completion.feedback.status_completion"
          class="status-text">
          <i class="fa fa-info-circle"></i>
          <span>{{ store.state.strings['node_access_completion_' + data.completion.feedback.status_completion]
            }}</span>
        </div>
        <div v-if="data.completion &&
          data.completion.feedback.completion">
          <UserFeedbackBlock :data="data.completion.feedback.completion[data.completion.feedback.status_completion]"
            title="restriction_before" />
        </div>


      </div>

      <!-- <div v-if="showFeedbackarea"
        :style="feedbackStyle"
        class="selectable"
        @mousedown.stop
        @mousemove.stop
        @mouseup.stop>
        <div v-if="data.completion && data.completion.feedback.status" class="status-text">
          <i class="fa fa-info-circle"></i>
          <span>{{ store.state.strings['node_access_' + data.completion.feedback.status] }}</span>
        </div>
        <div v-if="data.completion &&
            (data.completion.feedback.status == 'closed' || data.completion.feedback.status == 'not_accessible')">
          <UserFeedbackBlock
            :data="data.completion.feedback.restriction.before"
            title="restriction_before"
          />
        </div>
        <div v-if="data.completion &&
            (data.completion.feedback.status == 'not_accessible')">
          <UserFeedbackBlock
            :data="data.completion.feedback.completion.before"
            title="restriction_completion_before"
          />
        </div>
        <div v-else>
          <UserFeedbackBlock
            v-if="data.completion && data.completion.feedback.status == 'not_accessible'"
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
        </div>
        <div
          v-if="!data.completion"
        >
          {{ store.state.strings.node_access_nothing_defined }}
        </div>
      </div> -->

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
  position: relative;
  justify-content: center;
  align-items: center;
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  text-align: center;
  z-index: 3,
}

.card-container.no-bottom-radius {
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}


.toggle-button {
  cursor: pointer;
  border-radius: 8px;
  /* This is the default state */
  transition: background-color 0.3s ease, border-radius 0.3s ease;
}

.toggle-button.no-bottom-radius {
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
}

.toggle-button:hover {
  background-color: rgb(123, 127, 132);
}

.fa-comment {
  font-size: 20px;
}

.fa-comment-mobile {
  font-size: 50px !important;
}

.card-container:hover {
  background-color: rgb(213, 207, 207);
}

.selectable {
  user-select: text;
  cursor: text;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
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
