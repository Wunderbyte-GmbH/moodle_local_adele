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
import { computed } from 'vue';
import { useStore } from 'vuex';

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
  if (data.imagepaths[props.data.course_id]) {
    return data.imagepaths[props.data.course_id]
  }
  return null
}

</script>

<template>
  <div>
    <div
      class="card test"
      :style="[{ minHeight: '200px', width: '400px' }]"
    >
      <div class="card-header text-center">
        <div class="row">
          <div class="col-10">
            <h5>
              {{ data.fullname || store.state.strings.nodes_collection }}
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
</template>
<style scoped>
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