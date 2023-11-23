<script setup>
import { defineProps, ref, computed } from 'vue';

const searchTerm = ref('');

function onDragStart(event, data) {
  if (event.dataTransfer) {
    event.dataTransfer.setData('application/vueflow', 'custom');
    event.dataTransfer.setData('application/data', JSON.stringify(data));
    event.dataTransfer.effectAllowed = 'move';
  }
}

const props = defineProps({
  courses: Array,
  strings: Object,
});

const filteredCourses = computed(() => {
  if(searchTerm.value.toLowerCase().startsWith('#')){
    return props.courses.filter(course =>
      course.s1.toLowerCase().includes(searchTerm.value.toLowerCase().slice(1))
    );
  }
  return props.courses.filter(course =>
    course.fullname.toLowerCase().includes(searchTerm.value.toLowerCase())
  );
});

</script>

<template>
  <aside>
    <div class="description" type="text">{{ strings.fromavailablecourses }}</div>
    <input v-model="searchTerm" placeholder="Search courses" />
    <div class="nodes-container">
      <div class="nodes">
        <template v-for="course in filteredCourses" :key="course.id">
          <div class="vue-flow__node-input" :draggable="true" @dragstart="onDragStart($event, course)" :data ="course">
            {{ course.fullname }}
          </div>
        </template>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.nodes-container {
  margin-top: 20px;
  height: 100%;
  overflow-y: auto;
}
</style>