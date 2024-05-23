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
import { computed, ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import CompletionOutPutItem from '../completion/CompletionOutPutItem.vue'
import RestrictionOutPutItem from '../restriction/RestrictionOutPutItem.vue'
import UserInformation from '../nodes_items/UserInformation.vue';
import ProgressBar from '../nodes_items/ProgressBar.vue';
import NodeInformation from '../nodes_items/NodeInformation.vue';

// Load Store 
const store = useStore();
const date = ref({})
const includedCourses = ref([])

const parentnode = ref({})
const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  learningpath: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits([
  'node-clicked',
]);
const active = ref(false)

onMounted(() => {
  const userpath = props.learningpath
  userpath.json.tree.nodes.forEach((node) => {
    if (props.data.node_id == node.id) {
      parentnode.value = node
      if (node.restriction && node.restriction.nodes) {
      node.restriction.nodes.forEach((restrictionnode) => {
        if(restrictionnode.data.label == 'timed'){
          date.value = restrictionnode.data.value
        }
      })
      }
      if (node.parentCourse.includes('starting_node')) {
      isParentNode.value = true;
      }
     }
  })

  if (props.data.course_node_id && store.state.availablecourses) {
    props.data.course_node_id.forEach((course_id) => {
      const course = store.state.availablecourses.find(course => course.course_node_id[0] === course_id)
      if (course) {
        includedCourses.value.push({
          id: course.course_node_id[0],
          name: course.fullname,
        })
      }
    })
  }
  if (props.data.completion.singlerestrictionnode && props.data.completion.singlerestrictionnode.length == 0) {
    active.value = true
  } else if (props.data.completion.restrictionnode && props.data.completion.restrictionnode.valid) {
    active.value = true
  }
})

const cover_image = computed(() => get_cover_image(props.data));

const get_cover_image = (data) => {
  if (data.selected_course_image) {
    return data.selected_course_image
  } else if (data.selected_image) {
    return data.selected_image
  } else if (data.image_paths) {
    return data.image_paths
  }
}

// Dynamic background color based on data.completion
const nodeBackgroundColor = computed(() => {
  if (props.data.completion.completionnode) {
    return {
      backgroundColor: props.data.completion.completionnode.valid ? store.state.strings.LIGHT_STEEL_BLUE : store.state.strings.LIGHT_GRAY,
    };
  }
  return {
    backgroundColor: props.data.completion ? store.state.strings.LIGHT_STEEL_BLUE : store.state.strings.LIGHT_GRAY,
  };
});

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

const isParentNode = ref(false);

const parentStyle = {
  borderColor: store.state.strings.DEEP_SKY_BLUE,
  borderWidth: '3px',
};

const goToCourse = () => {
  let course_link = '/course/view.php?id=' + props.data.course_node_id
  window.open(course_link, '_blank');
}

</script>

<template>
  <div
    @click="emit('node-clicked', props.data)"
  >
    <div
      class="card"
      :style="[{ minHeight: '200px', width: '400px' }, parentStyle]"
    >
      <div class="card-header text-center">
        <NodeInformation 
          :data
          :parentnode
        /> 
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
        :class="(active || store.state.view == 'teacher') ? 'active-node' : 'inactive-node'"
        :style="[nodeBackgroundColor]"
      >
        <div v-if="store.state.learningpath">
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
                <i 
                  :class="active ? 'fa fa-play' : 'fa fa-lock'"
                />
              </button>
            </div>
          </div>
          <div 
            class="row mb-2"
          >
            <div class="col-4 text-left">
              <b>
                {{ store.state.strings.nodes_progress }}
              </b>
            </div>
            <div 
              class="col-8" 
              style="display: flex; justify-content: end;"
            >
              <ProgressBar :progress="data.progress" />
            </div>
            <div v-if="store.state.view == 'teacher' && data.manualrestriction">
              <RestrictionOutPutItem 
                :data="data"
              />
            </div>
            <div v-if="store.state.view == 'teacher' && data.manualcompletion">
              <CompletionOutPutItem :data="data" />
            </div>
          </div>
        </div>
      </div>
      <div
        v-if="data"
        class="card-footer"
      >
        <UserInformation 
          :data="data" 
        />
      </div>
    </div>
    <Handle 
      v-if="store.state.view!='teacher' && store.state.view!='student'"
      id="target" 
      type="target" 
      :position="Position.Top" 
      :style="handleStyle" 
    />
    <Handle 
      v-if="store.state.view!='teacher' && store.state.view!='student'"
      id="source" 
      type="source" 
      :position="Position.Bottom" 
      :style="handleStyle" 
    />
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
.active-node{
  z-index: 100;
}
.inactive-node{
  pointer-events: none;
  opacity: 0.5;
}

.table-hover tbody tr:hover {
  background-color: #f5f5f5;
  
}
.table-container {
  width: 300px;
  position: absolute;
  z-index: 100;
}
.table-container-left {
  transform: translate(-50%, 0);
}

.fancy-table {
  border-radius: 10px; /* Rounded corners */
}

.fancy-table thead th {
  background-color: #3498db; /* Header background color */
  color: #fff; /* Header text color */
}

.fancy-table tbody {
  background-color: #ecf0f1; /* Body background color */
}

.fancy-table tbody tr:nth-child(odd) {
  background-color: #d1d1d1; /* Alternate row background color */
}

.fancy-table tbody tr:hover {
  background-color: #bdc3c7; /* Hovered row background color */
}
.starting-node {
  font-size: 20px; /* Adjust the font size as needed */
  font-weight: bold; /* Make the text bold */
  display: flex; /* Align icon and text horizontally */
  align-items: center; /* Center items vertically */
}

</style>