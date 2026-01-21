<script setup lang="ts">
    import { computed } from 'vue';
    import { useStore } from 'vuex';

    interface FinishedCourses {
      [key: string]: boolean;
    }


    const store = useStore();
    const props = defineProps<{
      minCourses: number;
      finishedCourses: FinishedCourses;
    }>();

    // Neutral background for course completion dots
    const backgroundColor = computed(() => store.state.strings.LIGHT_GRAY);
    const minCoursesArray = computed(() => Array.from({ length: props.minCourses }, (_, i) => i));
    const finishedCoursesCount = computed(() => {
      return Object.values(props.finishedCourses).filter(value => value === true).length;
    });

</script>

<template>
  <div
    class="icon-container"
  >
    <div
      class="row"
      style="margin-right: -5px !important;"
    >
      <span
        v-for="index in minCoursesArray"
        :key="index"
        class="coursecompletion"
        :style="{ backgroundColor: backgroundColor }"
      >
        <div v-if="finishedCoursesCount > index">
          <i
            class="fas fa-check"
          />
        </div>
      </span>
    </div>
  </div>
</template>

<style scoped>

.icon-container {
  position: absolute;
  top: 30px;
  right: -20px;
}

.coursecompletion {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid rgba(0,0,0,0.15);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.28);
}

.fa-check {
  font-size: 18px;
  color: #555;
}
</style>
