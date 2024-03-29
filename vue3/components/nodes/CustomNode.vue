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
import { computed, onMounted, ref, watch  } from 'vue';
import { useStore } from 'vuex';
import OverviewRestrictionCompletion from '../nodes_items/OverviewRestrictionCompletion.vue';
import NodeFeedbackArea from '../nodes_items/NodeFeedbackArea.vue';

// Load Store 
const store = useStore();
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

const courses = ref([])
const dataValue = ref('')
const learningmodule = ref({})
const cover_image = ref(null)

onMounted(() => {
  dataValue.value = props.data
  if (dataValue.value.selected_course_image) {
    cover_image.value = dataValue.value.selected_course_image
  } else if (dataValue.value.selected_image) {
    cover_image.value = dataValue.value.selected_image
  } else if (dataValue.value.image_paths) {
    cover_image.value = dataValue.value.image_paths
  }
  let parsedLearningModule = props.learningpath.json
  if ( typeof parsedLearningModule == 'string' && parsedLearningModule != '') {
    parsedLearningModule = JSON.parse(props.learningpath.json)
  }
  if (props.learningpath.json && props.learningpath.json.modules) {
    learningmodule.value = props.learningpath.json.modules
  } else {
    learningmodule.value = {}
  }
  getCourseNamesIds()
})

const getCourseNamesIds = () => {
  if (store.state.availablecourses) {
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
}

// watch values from selected node
watch(() => store.state.availablecourses, () => {
  getCourseNamesIds()
}, { deep: true } );

// watch values from selected node
watch(() => props.learningpath, () => {
  if (props.learningpath.json && props.learningpath.json.modules) {
    learningmodule.value = props.learningpath.json.modules
  } else {
    learningmodule.value = {}
  }
}, { deep: true } );

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

// Set the node that handle has been clicked
const setStartNode = (node_id) => {
  store.commit('setstartNode', {
    startnode: node_id, 
  });
};

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

const childStyle = {
  borderColor: store.state.strings.GRAY,
  borderWidth: '2px',
};

</script>

<template>
  <div>
    <div
      class="card"
      :style="[{ minHeight: '200px', width: '400px' }, childStyle]"
    >
      <OverviewRestrictionCompletion 
        :node="data" 
        :learningpath="props.learningpath"
      />
      <div class="card-header text-center">
        <div class="row align-items-center">
          <div class="col">
            <h5>
              {{ data.fullname || store.state.strings.nodes_collection }}
            </h5>
          </div>
          <div v-if="store.state.view!='teacher'" class="col-auto">
            <button 
              type="button" 
              class="btn btn-primary" 
              data-toggle="modal" 
              data-target="#nodeModal"
              @click="setNodeModal"
            >
              <i class="fa fa-edit" /> {{ store.state.strings.nodes_edit }}
            </button>
          </div>
        </div>
      </div>

      <div class="card-body">
        <div 
          v-if="cover_image"
          class="card-img dashboard-card-img" 
          :style="{ 
            height: '10rem', 
            backgroundImage: 'url(' + cover_image + ')',
            backgroundSize: 'cover',
            backgroundPosition: 'center'
          }"
        />
        <div v-if="Object.keys(learningmodule).length > 0 && store.state.view!='teacher'">
          <h5 class="card-title">
            {{ store.state.strings.nodes_learning_module }}
          </h5>
          <div v-if="dataValue">
            <select 
              v-model="dataValue.module"
              class="form-select form-control"
              @change="changeModule"
            >
              <option 
                value="" 
                selected 
                disabled
              >
                {{ store.state.strings.nodes_select_module }}
              </option>
              <option 
                v-for="module in learningmodule" 
                :key="module.id" 
                :value="module.id"
              >
                {{ module.name }}
              </option>
            </select>
          </div>
        </div>
        <h5 class="card-title">
          {{ store.state.strings.nodes_included_courses }}
        </h5>
        <div 
          v-for="(value, key) in courses" 
          :key="key" 
          class="card-text"
        >
          <div class="fullname-container">
            {{ value.fullname }}
          </div>
        </div>
        <div 
          v-if="store.state.view!='teacher'"
          class="row align-items-center"
        >
          <div class="col">
            <button 
              type="button" 
              class="btn btn-secondary" 
              :style="{backgroundColor: store.state.strings.LIGHT_STEEL_BLUE}"
              @click="setRestrictionView"
            >
              <i class="fa-solid fa-key" /> 
              {{ store.state.strings.nodes_edit_restriction }}
            </button>
          </div>
          <div class="col-auto">
            <button 
              type="button" 
              class="btn btn-secondary" 
              :style="{backgroundColor: store.state.strings.DARK_ORANGE}"
              @click="setPretestView"
            >
              <i class="fa-solid fa-check-to-slot" /> 
              {{ store.state.strings.nodes_edit_completion }}
            </button>
          </div>
        </div>
      </div>
      <div 
        v-if="dataValue"
        class="card-footer"
      >
        <NodeFeedbackArea :data="dataValue" />
      </div>
    </div>
    <Handle 
      id="target" 
      type="target" 
      :position="Position.Top" 
      :style="handleStyle" 
      @mousedown="() => setStartNode(data.node_id)" 
    />
    <Handle 
      id="source" 
      type="source" 
      :position="Position.Bottom" 
      :style="handleStyle" 
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

.form-select {
  max-width: 100%; /* Set a maximum width for the select */
}
</style>