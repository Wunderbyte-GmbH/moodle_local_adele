<!-- // This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->
 
<script setup>
// Import needed libraries
import { defineProps, ref, computed } from 'vue';

// Reference on searchTerm
const searchTerm = ref('');

// Function sets up data for nodes
function onDragStart(event, data) {
  if (event.dataTransfer) {
    event.dataTransfer.setData('application/vueflow', 'custom');
    event.dataTransfer.setData('application/data', JSON.stringify(data));
    event.dataTransfer.effectAllowed = 'move';
  }
}

// Defined props from the parent component
const props = defineProps({
  courses: Array,
  strings: Object,
});

// Calculate searched courses
const filteredCourses = computed(() => {
  if(searchTerm.value.toLowerCase().startsWith('#')){
    return props.courses.filter(course =>
      course.tags.toLowerCase().includes(searchTerm.value.toLowerCase().slice(1))
    );
  }
  return props.courses.filter(course =>
    course.fullname.toLowerCase().includes(searchTerm.value.toLowerCase())
  );
});

</script>

<template>
  <aside class="col-md-2" style="min-width: 10% !important;"> <!-- Adjust the width as needed -->
    <div type="text">{{ strings.fromavailablecourses }}</div>
    <div type="text">{{ strings.tagsearch_description }}</div>
    <input class="form-control" v-model="searchTerm" :placeholder="strings.placeholder_search" />
    <div class="learning-path-nodes-container">
      <div class="nodes">
        <template v-for="course in filteredCourses" :key="course.id">
          <div class="vue-flow__node-input mt-1" :draggable="true" @dragstart="onDragStart($event, course)" :data="course" style="width: 100%;">
            {{ course.fullname }}
          </div>
        </template>
      </div>
    </div>
</aside>
</template>

<style scoped>
.learning-path-nodes-container {
  margin-top: 20px;
  height: 80%;
  overflow-y: auto;
}
</style>