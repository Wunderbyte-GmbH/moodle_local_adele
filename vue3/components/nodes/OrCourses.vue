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
import { Handle, Position } from '@vue-flow/core'
import { useStore } from 'vuex';
import OverviewRestrictionCompletion from '../nodes_items/OverviewRestrictionCompletion.vue';
import { computed, onMounted, ref, watch } from 'vue';

const courses = ref([])
const cardHeight = ref(200);
const dataValue = ref('')

 const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});
// Load Store 
const store = useStore();

const emit = defineEmits(['typeChange']);

// Set node data for the modal
const setNodeModal = () => {
  store.state.node = props.data
};

// Set node data for the modal
const setPretestView = () => {
  store.state.node = props.data
  store.state.editingpretest = true
  store.state.editingadding = false
  store.state.editingrestriction = false
};

// Set node data for the modal
const setRestrictionView = () => {
  store.state.node = props.data
  store.state.editingpretest = false
  store.state.editingadding = false
  store.state.editingrestriction = true
};

onMounted(() => {
  getCourseNamesIds() 
  calculateHeight(courses.value.length)
  dataValue.value = props.data
})

const getCourseNamesIds = () => {
  courses.value = []
  store.state.availablecourses.forEach(course => {
    if (props.data.course_node_id.includes(course.course_node_id[0])) {
      courses.value.push({
        fullname : course.fullname,
        id : [course.course_node_id[0]]
      })
    }
  });
}

const calculateHeight = (length) => {
  const minHeight = 275;
  const numberOfCourses = length;
  const calculatedHeight = minHeight + numberOfCourses * 60;
  cardHeight.value = calculatedHeight;
}

const removeCourse = (id) => {
  courses.value = courses.value.filter(course => course.id !== id);
  store.state.learninggoal[0].json.tree.nodes.forEach((node) => {
    if (node.id == props.data.node_id) {
      dataValue.value.course_node_id = removeElement(node.data.course_node_id, id[0]);
      if (courses.value.length == 1) {
        emit('typeChange', node)
      }
    }
  })
}

const removeElement = (array, elementToRemove) => {
  const index = array.indexOf(elementToRemove);
  if (index !== -1) {
    array.splice(index, 1);
  }
  return array
};

// watch values from selected node
watch(() => props.data, () => {
  getCourseNamesIds()

}, { deep: true } );

// watch values from selected node
watch(() => courses.value, () => {
  calculateHeight(courses.value.length)
}, { deep: true } );

const targetHandleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

// Set the node that handle has been clicked
const setStartNode = (node_id) => {
  store.commit('setstartNode', {
    startnode: node_id, 
  });
};
 
</script>
<template>
  <div>
    <div 
      class="custom-node text-center rounded p-3" 
      :style="{ height: cardHeight + 'px', width: '400px' }"
    >
      <div>
        <button 
          type="button" 
          class="btn btn-secondary" 
          @click="setRestrictionView"
        >
          <i class="fa fa-cogs" /> Edit Restrictions
        </button>
      </div>
      <div class="mb-2">
        <strong>{{ store.state.strings.node_coursefullname }}</strong> {{ data.fullname }}
      </div>
      <div class="card-body">
        <h5 class="card-title">
          Included Courses
        </h5>
        <div 
          v-for="(value, key) in courses" 
          :key="key" 
          class="card-text"
        >
          <div class="fullname-container">
            {{ value.fullname }}
            <button 
              type="button" 
              class="btn btn-danger btn-sm trash-button" 
              @click="removeCourse(value.id)"
            >
              <i class="fa fa-trash" />
            </button>
          </div>
        </div>
      </div>
      <div>
        <button 
          type="button" 
          class="btn btn-primary" 
          data-toggle="modal" 
          data-target="#nodeModal"
          @click="setNodeModal"
        >
          <i class="fa fa-edit" /> {{ store.state.strings.edit_course_node }}
        </button>
        <button 
          type="button" 
          class="btn btn-secondary" 
          @click="setPretestView"
        >
          <i class="fa fa-tasks" /> {{ store.state.strings.edit_node_pretest }}
        </button>
      </div>
      <OverviewRestrictionCompletion :node="data" />
    </div>
    <Handle 
      id="target" 
      type="target" 
      :position="Position.Top" 
      :style="targetHandleStyle" 
      @mousedown="() => setStartNode(data.node_id)"
    />
    <Handle 
      id="source" 
      type="source" 
      :position="Position.Bottom" 
      :style="targetHandleStyle" 
      @mousedown="() => setStartNode(data.node_id)"
    />
  </div>
</template>
<style scoped>
.custom-node {
  position: relative;
  background-color: white;
  padding: 10px;
  border: 1px solid #ccc;
}
.card-text {
  padding: 5px;
}
.fullname-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #f0f0f0; /* Set your desired background color */
  padding: 10px; /* Adjust padding as needed */
  border-radius: 10px; /* Set your desired border-radius */
}

.trash-button {
  margin-left: 10px; /* Adjust margin as needed */
}

</style>

