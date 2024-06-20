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
import { computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import ExpandNodeInformation from '../nodes_items/ExpandNodeInformation.vue';

// Load Store
const store = useStore();

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const goToCourse = () => {
  let course_link = '/course/view.php?id=' + props.data.course_id
  window.open(course_link, '_blank');
}

const cover_image = computed(() => get_cover_image(props.data));

const get_cover_image = (data) => {
  if (data.imagepaths && data.imagepaths[props.data.course_id]) {
    return data.imagepaths[props.data.course_id]
  }
  return null
}

const courses = computed(() => {
  if (
    !store.state.availablecourses ||
    !props.data.course_id
  ) {
    return [];
  }
  return store.state.availablecourses.filter(course =>
    props.data.course_id == course.course_node_id[0]
    ).map(course => ({
      fullname: course.fullname,
      description: course.summary,
      id: [course.course_node_id[0]]
    })
  )}
);
onMounted(() => {
  setTimeout(() => {
    props.data.showCard = true
  }, 200)
})

</script>

<template>
  <transition name="unfold">
    <div v-if="data.showCard">
      <div
        class="card test"
        :style="[{ minHeight: '200px', width: '400px' }]"
      >
        <div class="card-header text-center">
          <ExpandNodeInformation
            :courses
          />
          <div class="row">
            <div class="col-10">
              <h5 v-if="courses[0]">
                {{ courses[0].fullname }}
              </h5>
              <h5 v-else>
                Subcourse
              </h5>
            </div>
          </div>
        </div>
        <div
          class="card-body"
          :class="active ? 'active-node' : 'inactive-node'"
          :style="[nodeBackgroundColor]"
        >
          <div
            class="card-img dashboard-card-img mb-2"
            :style="{
              height: '10rem',
              backgroundImage: cover_image ? 'url(' + cover_image + ')' : 'none',
              backgroundSize: 'cover',
              backgroundPosition: 'center',
              backgroundColor: cover_image ? '' : '#cccccc'
            }"
          >
            <div class="overlay">
              <button
                class="icon-link"
                @click="goToCourse"
              >
                <i class="fa fa-play" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
</transition>
</template>
<style scoped>
/* Starting state for entering */
.unfold-enter-active, .unfold-leave-active {
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
  visibility: visible;
}

.unfold-enter-from, .unfold-leave-to {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: left;
}

.unfold-enter-to, .unfold-leave-from {
  transform: scaleX(1);
  opacity: 1;
  transform-origin: left;
}

.overlay {
  position: relative;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background-color: rgba(0, 0, 0, 0.4); /* Semi-transparent gray */
  display: flex;
  justify-content: center;
  align-items: center;
  width: 70%; /* Adjust width as needed */
  height: 50%; /* Adjust height as needed */
  border-radius: 15px; /* Rounded edges */
}
.icon-link {
  border: none;
  background: none;
  color: white;
  font-size: 30px;
  cursor: pointer;
  padding: 10px;
  margin: 0 15px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
}
.icon-link:hover {
  color: lightgray; /* Hover effect */
}
</style>