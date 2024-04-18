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
import { computed, ref, onMounted } from 'vue'
import { useStore } from 'vuex'
import CompletionOutPutItem from '../completion/CompletionOutPutItem.vue'
import RestrictionOutPutItem from '../restriction/RestrictionOutPutItem.vue'
import UserInformation from '../nodes_items/UserInformation.vue';
import NodeFeedbackArea from '../nodes_items/NodeFeedbackArea.vue'
import CourseCarousel from '../nodes_items/CourseCarousel.vue'
import CourseCompletion from '../nodes_items/CourseCompletion.vue'
import ProgressBar from '../nodes_items/ProgressBar.vue'
import ExpandedCourses from '../nodes_items/ExpandedCourses.vue'
import NodeInformation from '../nodes_items/NodeInformation.vue'

// Load Store 
const store = useStore();
const date = ref({})

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

const stageType = ref('parallel')
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
let min_courses = 1
let finishedCourses = {}

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
       if (node.completion && node.completion.nodes) {
        node.completion.nodes.forEach((completionnode) => {
          if(completionnode.data.label == 'course_completed' &&
            completionnode.data.value.min_courses > 1){
              stageType.value = 'non_parallel'
              min_courses = completionnode.data.value.min_courses
              if (userpath.json.user_path_relation[node.id] &&
                userpath.json.user_path_relation[node.id].completioncriteria) {
                finishedCourses = userpath.json.user_path_relation[node.id].completioncriteria.course_completed
              }
          }
        })
       }
     }
  })
  if (store.state.view!='student') {
    active.value = true
  }
  else if (props.data.completion.singlerestrictionnode.length == 0) {
    active.value = true
  } else if (props.data.completion.restrictionnode.valid) {
    active.value = true
  }
})
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

const isCompletionVisible = ref(false);
const isRestrictionVisible = ref(false);
const isParentNode = ref(false);
const courseExpanded = ref(false);

const toggleTable = (condition) => {
  const otherCondition = condition == store.state.strings.nodes_completion ? store.state.strings.nodes_completion : store.state.strings.nodes_completion;
  const conditionRef = eval(`is${condition}Visible`);
  conditionRef.value = !conditionRef.value;
  const otherconditionRef = eval(`is${otherCondition}Visible`);
  otherconditionRef.value = false;
};

const parentStyle = {
  borderColor: store.state.strings.DEEP_SKY_BLUE,
  borderWidth: '3px',
};

const expandCourses = () => {
  courseExpanded.value = !courseExpanded.value
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
      <div 
        :class="stageType=='parallel' ? 'parallel' : 'non_parallel'"
      />
      <div class="card-header text-center">
        <NodeInformation 
          :data
          :parentnode
        /> 
        <div class="row">
          <div class="col-4">
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
        <div v-if="min_courses > 1">
          <CourseCompletion 
            :min-courses="min_courses"
            :finished-courses="finishedCourses"
          />
        </div>
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
              v-if="active"
              class="icon-link"
              @click="expandCourses"
            >
              <i :class="['fa', courseExpanded ? 'fa-circle-minus' : 'fa-circle-plus']" />
            </button>
            <button 
              v-else
              class="icon-link"
            >
              <i :class="'fa fa-lock'" />
            </button>
          </div>
        </div>
        <div v-if="store.state.learningpath && store.state.view=='student'">
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
          </div>
        </div>
        <div v-else>
          <div v-if="data.manualrestriction">
            <RestrictionOutPutItem 
              :data="data"
            />
          </div>
          <div v-if="data.manualcompletion">
            <CompletionOutPutItem :data="data" />
          </div>
          <div class="row">
            <div class="col-md-6">
              <div v-if="data.completion.singlerestrictionnode">
                <button 
                  class="btn btn-link" 
                  aria-expanded="false" 
                  aria-controls="collapseTable"
                  :disabled="!active"
                  @click="toggleTable('Restriction')"
                >
                  {{ isRestrictionVisible ? 'Hide Restriction' : 'Show Restriction' }}
                </button>
                <div 
                  v-show="isRestrictionVisible" 
                  class="table-container table-container-left"
                >
                  <div v-if="Object.keys(data.completion.singlerestrictionnode).length > 0">
                    <table 
                      class="table table-bordered table-hover fancy-table" 
                      style="right: -150%;"
                    >
                      <thead class="thead-light">
                        <tr>
                          <th>
                            {{ store.state.strings.nodes_table_key }}
                          </th>
                          <th>
                            {{ store.state.strings.nodes_table_checkmark }}
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr 
                          v-for="(value, key) in data.completion.singlerestrictionnode" 
                          :key="key"
                        >
                          <td>{{ key }}</td>
                          <td>
                            {{ value }}
                            <span 
                              v-if="value" 
                              class="text-success"
                            >
                              &#10004;
                            </span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div v-else>
                    <div class="card">
                      <div class="card-body">
                        {{ store.state.strings.nodes_no_restriction_defined }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div v-if="data.completion.singlecompletionnode">
                <button 
                  class="btn btn-link" 
                  aria-expanded="false" 
                  aria-controls="collapseTable"
                  :disabled="!active"
                  @click="toggleTable('Completion')"
                >
                  {{ isCompletionVisible ? store.state.strings.nodes_hide_completion : store.state.strings.nodes_show_completion }}
                </button>
                <div
                  v-show="isCompletionVisible" 
                  class="table-container"
                >
                  <div v-if="Object.keys(data.completion.singlecompletionnode).length > 0">
                    <table class="table table-bordered table-hover fancy-table">
                      <thead class="thead-light">
                        <tr>
                          <th>
                            {{ store.state.strings.nodes_table_key }}
                          </th>
                          <th>
                            {{ store.state.strings.nodes_table_checkmark }}
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr 
                          v-for="(value, key) in data.completion.singlecompletionnode" 
                          :key="key"
                        >
                          <td>{{ key }}</td>
                          <td>
                            {{ value }}
                            <span 
                              v-if="value" 
                              class="text-success"
                            >
                              &#10004;
                            </span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div v-else>
                    <div class="card">
                      <div class="card-body">
                        {{ store.state.strings.nodes_no_completion_defined }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="courseExpanded">
          <ExpandedCourses :data="data" /> 
        </div>
      </div>
      <div 
        v-if="store.state.view=='student' && data"
        class="card-footer"
      >
        <!-- <NodeFeedbackArea :data="data" /> -->
        <UserInformation 
          :data="data"
        />
        <CourseCarousel :courses="props.data" />
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
.parallel::before,
.parallel::after {
  content: '';
  position: absolute;
  width: 100%;
  height: 100%;
  background: #ececec; /* Example background color */
  border-radius: 10px;
  border: 1px solid #ccc; /* Example border */
  z-index: -1;
}

.parallel::before {
  top: -20px;
  left: -20px;
  z-index: -1;
}

.parallel::after {
  top: -40px;
  left: -40px;
  z-index: -2;
}
.non_parallel::before,
.non_parallel::after {
  content: '';
  position: absolute;
  width: 100%;
  height: 100%;
  background: #ececec;
  border-radius: 10px;
  border: 1px solid #ccc;
  z-index: -1;
}

.non_parallel::before {
  transform: rotate(4deg);
  top: -20px;
  left: -20px;
}

.non_parallel::after {
  transform: rotate(-4deg);
  top: -20px;
  left: -20px;
}
</style>