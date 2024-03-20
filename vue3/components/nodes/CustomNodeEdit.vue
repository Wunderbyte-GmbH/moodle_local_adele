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
import { computed, ref, onMounted, watch } from 'vue';
import { useStore } from 'vuex';
import CompletionOutPutItem from '../completion/CompletionOutPutItem.vue'
import RestrictionOutPutItem from '../restriction/RestrictionOutPutItem.vue'
import OverviewRestrictionCompletion from '../nodes_items/OverviewRestrictionCompletion.vue';
import NodeFeedbackArea from '../nodes_items/NodeFeedbackArea.vue';
import ProgressBar from '../nodes_items/ProgressBar.vue';
import DateInfo from '../nodes_items/DateInfo.vue';
import CourseRating from '../nodes_items/CourseRating.vue';

// Load Store 
const store = useStore();
const date = ref({})
const includedCourses = ref([])
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
const active = ref(false)

onMounted(() => {
  const userpath = props.learningpath
  userpath.json.tree.nodes.forEach((node) => {
    if (props.data.node_id == node.id) {
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

  watch(() => store.state.availablecourses, () => {
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
  });
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

const childStyle = {
  borderColor: store.state.strings.GRAY,
  borderWidth: '2px',
};

</script>

<template>
  <div>
    <div 
      v-if="isParentNode"
      class="starting-node"
    >
      <i 
        class="fa-solid fa-arrow-down mr-2"
        :style="{color: store.state.strings.PUMPKIN}" 
      />
      {{ store.state.strings.nodes_potential_start }}
    </div>

    <div
      class="card test"
      :style="[{ minHeight: '200px', width: '400px' }, isParentNode ? parentStyle : childStyle]"
    >
      <div class="card-header text-center">
        <OverviewRestrictionCompletion 
          :node="data" 
          :learningpath="learningpath"
        />
        <div class="row">
          <div class="col-10">
            <h5>
              {{ data.fullname || store.state.strings.nodes_collection }}
            </h5>
          </div>
          <div 
            v-if="data.completion.completionnode.valid"
            class="col-2 d-flex justify-content-end"
          >
            <CourseRating :data="data" />
          </div>
        </div>
      </div>
      <div 
        class="card-body"
        :class="active ? 'active-node' : 'inactive-node'"
        :style="[nodeBackgroundColor]"
      >
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
          <div v-if="includedCourses" class="row mb-2">
            <div class="col-5 text-left">
              <b>
                {{ store.state.strings.nodes_courses }}
              </b> 
            </div>
            <div class="col-7">
              <ul 
                v-for="(value, key) in includedCourses" 
                :key="key"
              >
                <li>
                  <a 
                    :href="'/course/view.php?id=' + value.id"
                    :target="active ? '_blank' : ''" 
                  >
                    {{ value.name }}
                  </a>
                </li>
              </ul>
            </div>
          </div>
          <div v-if="date">
            <DateInfo :date="date" />
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
      </div>
      <div 
        v-if="data"
        class="card-footer"
      >
        <NodeFeedbackArea :data="data" />
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