<script setup>
function onDragStart(event, nodeType) {
  if (event.dataTransfer) {
    event.dataTransfer.setData('application/vueflow', nodeType)
    event.dataTransfer.effectAllowed = 'move'
  }
}
</script>

<script>
export default {
  props: {
    courses: Array, // Define a prop named 'message'
    strings: Object, // Define a prop named 'message'
  },
};
</script>

<template>
  <aside>
    <div class="description">{{ strings.fromavailablecourses }}</div>
    <div class="nodes">
      <template v-for="course in courses">
        <div class="vue-flow__node-input" :draggable="true" @dragstart="onDragStart($event, course.shortname)">{{course.fullname}}</div>
      </template>
    </div>
  </aside>
</template>
