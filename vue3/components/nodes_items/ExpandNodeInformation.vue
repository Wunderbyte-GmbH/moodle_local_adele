<script setup>
  // Import needed libraries
  import { computed, ref, watch } from 'vue';
  import { useStore } from 'vuex';
  import * as nodeColors from '../../config/nodeColors';

  const props = defineProps({
    courses: {
      type: Object,
      required: true,
    },
    data: {
      type: Object,
      required: false,
    },
    startanimation: {
      type: Boolean,
      default: true,
    },
  });
  const store = useStore();

  // Create a ref for conditions
  const showCard = ref(false);
  const iconClass = ref('fa-info');
  const iconState = ref('initial');

  const toggleCard = () => {
    showCard.value = !showCard.value
  }
  // Keep original card background
  const backgroundColor = computed(() => store.state.strings.LIGHT_GRAY)

  // Icon (circle) background based on course completion
  const iconBackgroundColor = computed(() =>
    isCourseCompleted.value
      ? nodeColors.courseNodeFinishedColor
      : nodeColors.courseNodeNotFinishedColor
  )

  // Determine completion based on course-specific completion criteria
  const isCourseCompleted = computed(() => {
    const courseId = props.data?.course_id;
    const completedMap = props.data?.completion?.completioncriteria?.course_completed?.completed;
    if (!courseId || !completedMap || typeof completedMap !== 'object') {
      return false;
    }
    return !!completedMap[courseId];
  });

  const triggerAnimation = () => {
    iconClass.value = isCourseCompleted.value ? 'fa-check' : 'fa-info';
  };

  // Watch for data changes and trigger animation
  watch(
    () => isCourseCompleted.value,
    () => {
      triggerAnimation();
    },
    { immediate: true }
  );

</script>

<template>
  <div
    class="icon-container "
    :class="{ 'card-hover': showCard, [courses.id + '_node_info_listener']: true}"
    @click="toggleCard()"
  >
    <div>
      <div
        class="information"
        :style="{ backgroundColor: iconBackgroundColor }"
      >
        <i :class="['fas', iconClass]" />
      </div>
      <transition name="unfold">
        <div
          v-if="showCard"
          class="additional-card"
          :style="{ backgroundColor: backgroundColor}"
        >
          <ul class="list-group">
            <li
              class="list-group-item"
            >
              <i class="fas fa-pencil" />
              <b>
                Description
              </b>
              <div class="list-group-text">
                <div v-if="courses[0] && courses[0].description">
                  <div v-html="courses[0].description"/>
                </div>
                <div v-else-if="courses[0] && courses[0].summary">
                  <div v-html="courses[0].summary"/>
                </div>
                <div v-else>
                  {{store.state.strings.nodes_no_description}}
                </div>
              </div>
            </li>
          </ul>
        </div>
      </transition>
    </div>
  </div>
</template>

<style scoped>

.unfold-enter-from, .unfold-leave-to {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: right;
}

.unfold-enter-to, .unfold-leave-from {
  transform: scaleX(1);
  opacity: 1;
  transform-origin: right;
}

.unfold-enter-active, .unfold-leave-active {
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
}

.list-group-text{
  text-align: left;
}

.icon-container {
  position: absolute;
  top: -25px;
  right: -25px;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
}

.information {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: none;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.28);
}

.information:hover {
  background-color: #ababab !important;
  box-shadow: 0 6px 8px rgba(0,0,0,0.2);
}

.information i {
  font-size: 28px;
  color: white;
}

.additional-card {
  width: 500px;
  padding: 10px;
  text-align: center;
  border-radius: 8px;
  position: absolute;
  left: -472px;
  top: 50px;
  text-align: start;
  z-index: 2;
}

</style>
