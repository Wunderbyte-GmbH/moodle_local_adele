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

<template>
  <div>
    <div
      id="courseModal"
      tabindex="-1"
      role="dialog"
      class="modal fade"
      aria-labelledby="exampleModalLabel"
      aria-hidden="true"
    >
      <div
        class="modal-dialog modal-lg"
        role="document"
      >
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5
              id="exampleModalLabel"
              class="modal-title"
            >
              {{ store.state.strings.modals_edit }} {{ fullname }}
            </h5>
            <button
              type="button"
              class="close text-white"
              data-dismiss="modal"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="fullname">
                <b>
                  {{ store.state.strings.modals_longname }}
                </b>
              </label>
              <input
                id="fullname"
                v-model="fullname"
                class="form-control"
                type="text"
              >
            </div>
            <div class="form-group">
              <label for="description">
                <b>
                  {{ store.state.strings.modals_description }}
                </b>
              </label>
              <textarea
                id="description"
                v-model="description"
                class="form-control"
                :placeholder="store.state.strings.modals_no_description"
                rows="5"
              />
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-dismiss="modal"
            >
              {{ store.state.strings.modals_close }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              data-dismiss="modal"
              @click="saveChanges"
            >
              {{ store.state.strings.modals_save_changes }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// import dependancies
import { useStore } from 'vuex'
import { ref, watch } from 'vue';

// define constants
const store = useStore();
const fullname = ref('')
const description = ref('')

const props = defineProps({
  learningpath: {
    type: Object,
    required: true,
  }
});

const emit = defineEmits([
  'save-edit-course',
]);

// updating changes and closing modal
const saveChanges = () => {
  store.commit('updatedCourseNode', {
    fullname: fullname.value,
    description: description.value,
    courseid: store.state.nodecourse[0],
  })
  emit('save-edit-course', {
    fullname: fullname.value,
    description: description.value,
  });
}

// watch values from selected node
watch(() => store.state.nodecourse, (newValue) => {
  if (
    store.state.node.course_node_id_description &&
    store.state.node.course_node_id_description[newValue]
  ) {
    fullname.value = store.state.node.course_node_id_description[newValue].fullname
    description.value = store.state.node.course_node_id_description[newValue].description
  } else {
    const foundcourse = findCourseByNodeId(newValue)
    fullname.value = foundcourse.fullname
    description.value = stripHtmlTags(foundcourse.summary)
  }
});

function stripHtmlTags(html) {
  const div = document.createElement('div');
  div.innerHTML = html;
  return div.textContent || div.innerText || '';
}

function findCourseByNodeId(nodeId) {
  return store.state.availablecourses.find(course => course.course_node_id.includes(nodeId[0]));
}

</script>

<style scoped>


</style>
