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
import { computed, onMounted, ref  } from 'vue';
import { useStore } from 'vuex';
import OverviewRestrictionCompletion from '../nodes_items/OverviewRestrictionCompletion.vue';

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

const courses = computed(() => {
  if (
    !store.state.availablecourses ||
    !props.data.course_node_id ||
    props.data.course_node_id.length === 0
  ) {
    return [];
  }
  return store.state.availablecourses.filter(course =>
      props.data.course_node_id.includes(course.course_node_id[0])
    ).map(course => ({
      fullname: course.fullname,
      id: [course.course_node_id[0]]
  }))
});
const dataValue = ref('')
const learningmodule = computed(() => {
  if (props.learningpath.json && props.learningpath.json.modules) {
    return props.learningpath.json.modules;
  }
  return {};
});
const cover_image = computed(() => get_cover_image(props.data));

onMounted(() => {
  dataValue.value = props.data
  let parsedLearningModule = props.learningpath.json
  if ( typeof parsedLearningModule == 'string' && parsedLearningModule != '') {
    parsedLearningModule = JSON.parse(props.learningpath.json)
  }
  if (props.learningpath.json && props.learningpath.json.modules) {
    learningmodule.value = props.learningpath.json.modules
  } else {
    learningmodule.value = {}
  }
})

const get_cover_image = (data) => {
  if (data.selected_course_image) {
    return data.selected_course_image
  } else if (data.selected_image) {
    return data.selected_image
  } else if (data.image_paths) {
    return data.image_paths
  }
}

// Set node data for the modal
const setNodeModal = () => {
  removeTooltips()
  store.state.node = props.data
};

// Set node data for the modal
const setPretestView = () => {
  removeTooltips()
  store.state.node = props.data
  store.state.editingpretest = true
  store.state.editingadding = false
  store.state.editingrestriction = false
};

// Set node data for the modal
const setRestrictionView = () => {
  removeTooltips()
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

const removeTooltips = () => {
  const tooltips = document.querySelectorAll('.tooltip');
  tooltips.forEach(tooltip => {
      tooltip.style.display = 'none';
  });
}

const emit = defineEmits([
  'change-module',
]);

const changeModule = (data) => {
  if(typeof data.module == "string") {
    delete data.module
  }
  emit('changeModule', data);
}

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
        </div>
      </div>
      <div class="card-body">
        <div
          class="card-img dashboard-card-img"
          :style="{
            height: '10rem',
            backgroundImage: cover_image ? 'url(' + cover_image + ')' : 'none',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            backgroundColor: cover_image ? '' : '#cccccc'
          }"
        >
          <div
            v-if="store.state.view!='teacher'"
            class="overlay"
          >
            <span
              :title="store.state.strings.nodes_edit_restriction"
              data-toggle="tooltip"
            >
              <button
                class="icon-link"
                @click="setRestrictionView"
              >
                <i class="fa fa-lock" />
              </button>
            </span>
            <span
              :title="store.state.strings.nodes_edit_completion"
              data-toggle="tooltip"
            >
              <button
                class="icon-link"
                @click="setPretestView"
              >
                <i
                  class="fa fa-tasks"
                />
              </button>
            </span>
            <span
              :title="store.state.strings.edit"
              data-toggle="tooltip"
            >
              <button
                class="icon-link"
                data-toggle="modal"
                data-target="#nodeModal"
                data-placement="right"
                @click="setNodeModal"
              >
                <i class="fa fa-pencil" />
              </button>
            </span>
          </div>
        </div>
        <div v-if="Object.keys(learningmodule).length > 0 && store.state.view!='teacher'">
          <h5 class="card-title">
            {{ store.state.strings.nodes_learning_module }}
          </h5>
          <div v-if="dataValue">
            <select
              v-model="dataValue.module"
              class="form-select form-control"
              @change="changeModule(dataValue)"
            >
              <option
                value=""
                selected
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