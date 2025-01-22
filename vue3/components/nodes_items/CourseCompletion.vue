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

    const backgroundColor = computed(() => store.state.strings.DARK_RED);
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
            class="fa fa-check"
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
  display: inline-block;
  width: 50px; /* Diameter of the round button */
  height: 50px; /* Diameter of the round button */
  border-radius: 50%; /* Makes the div round */
  border: 1px solid rgba(0,0,0,0.2);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.38); /* Adds depth with a shadow */
}

.fa-check {
  font-size: 30px; /* Make the icon larger */
  color: white; /* Change the color if needed */
  margin-left: 10px;
  margin-top: 10px;
}
</style>
