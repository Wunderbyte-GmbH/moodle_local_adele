<script setup>
import { ref, computed } from 'vue';

const searchTerm = ref('');

function onDragStart(event, nodeType) {
  if (event.dataTransfer) {
    event.dataTransfer.setData('application/vueflow', nodeType)
    event.dataTransfer.effectAllowed = 'move'
  }
}
const props = defineProps({
  courses: Array,
  strings: Object,
});

const filteredCourses = computed(() => {
  return props.courses.filter(course =>
    course.fullname.toLowerCase().includes(searchTerm.value.toLowerCase())
  );
});

</script>

<template>
  <aside>
    <div class="description" type="text">{{ strings.fromavailablecourses }}</div>

    <input v-model="searchTerm" placeholder="Search courses" />
    <div class="nodes">
      <template v-for="course in filteredCourses">
        <div class="vue-flow__node-input" :draggable="true" @dragstart="onDragStart($event, course.shortname)">
          {{course.fullname}}
        </div>
      </template>
    </div>
  </aside>
</template>