<script setup lang="ts">

  interface CourseProps {
    course_node_id: string[];
  }
  const props = defineProps<{
    courses: CourseProps;
  }>();

</script>

<template>
  <div
    class="row"
  >
    <div
      class="col-12 text-center"
    >
      <span
        v-for="course_node_id in props.courses.course_node_id"
        :key="course_node_id"
        class="dot"
      />
    </div>
  </div>
</template>

<style scoped>
.dot {
  height: 10px; /* Size of dot */
  width: 10px; /* Size of dot */
  background-color: black; /* Dot color */
  border-radius: 50%; /* Make it round */
  display: inline-block; /* Align dots next to each other */
  margin-right: 5px; /* Space between dots */
}
</style>
