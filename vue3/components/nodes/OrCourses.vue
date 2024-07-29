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
import { computed, onMounted, ref } from 'vue';
import ProgressBar from '../nodes_items/ProgressBar.vue';
import CourseCarousel from '../nodes_items/CourseCarousel.vue';
import UserInformation from '../nodes_items/UserInformation.vue';
import NodeInformation from '../nodes_items/NodeInformation.vue';
import CourseCompletion from '../nodes_items/CourseCompletion.vue';
import ExpandedCourses from '../nodes_items/ExpandedCourses.vue';

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
    })
  )}
);

const cardHeight = computed(() => {
  const minHeight = 275
  if (props.editorview) {
    return  minHeight + courses.value.length * 60
  }
  return  minHeight
})

const dataValue = ref('')
const learningmodule = ref({})
const cover_image = computed(() => get_cover_image(props.data));

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  learningpath: {
    type: Object,
    required: true,
  },
  editorview: {
    type: Boolean,
    required: true,
  },
});
// Load Store
const store = useStore();
const emit = defineEmits([
  'typeChange',
  'change-module',
]);

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
  dataValue.value = props.data
  cover_image.value = get_cover_image(dataValue.value)
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

const removeCourse = (id) => {
  courses.value = courses.value.filter(course => course.id !== id);
  store.state.learningpath.json.tree.nodes.forEach((node) => {
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

let min_courses = 1

const targetHandleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

// Set the node that handle has been clicked
const setStartNode = (node_id) => {
  store.commit('setstartNode', {
    startnode: node_id,
  });
};

const changeModule = (data) => {
  if(typeof data.module == "string") {
    delete data.module
  }
  emit('changeModule', data);
}

const childStyle = {
  borderColor: store.state.strings.GRAY,
  borderWidth: '2px',
};

const nodeBackgroundColor = computed(() => {
  if (!props.editorview) {
    return store.state.strings.LIGHT_GRAY
  }
  return ''
});

const courseExpanded = ref(false);
const isBlocked = ref(false);
const expandCourses = () => {
  courseExpanded.value = !courseExpanded.value
  isBlocked.value = true
}

const enableButton = () => {
  isBlocked.value = false
};

</script>
<template>
  <div>
    <div
      class="card"
      :style="[{ minHeight: cardHeight + 'px', width: '400px' }, childStyle]"
    >
      <div
        :class="!editorview ? 'non_parallel' : ''"
      >
        <div class="card-header text-center">
          <NodeInformation
          v-if="!editorview"
            :data
            :parentnode
          />
          <div class="row align-items-center">
            <div class="col">
              <h5>
                {{ data.fullname || store.state.strings.nodes_collection }}
              </h5>
            </div>
          </div>
        </div>

        <div
          class="card-body"
          :style="{backgroundColor: nodeBackgroundColor}"
        >
        <div v-if="!editorview && min_courses > 1">
          <CourseCompletion
            :min-courses="min_courses"
            :finished-courses="{}"
          />
        </div>
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
              v-if="store.state.view!='teacher' && editorview"
              class="overlay"
            >
              <button
                class="icon-link"
                @click="setRestrictionView"
              >
                <i class="fa fa-lock" />
              </button>
              <button
                class="icon-link"
                @click="setPretestView"
              >
                <i
                  class="fa fa-tasks"
                />
              </button>
              <button
                class="icon-link"
                data-toggle="modal"
                data-target="#nodeModal"
                @click="setNodeModal"
              >
                <i class="fa fa-pencil" />
              </button>
            </div>
            <div
              v-else-if="!editorview"
              class="overlay"
            >
              <button
                class="icon-link"
                :disabled="isBlocked"
                @click="expandCourses"
              >
                <i :class="['fa', courseExpanded ? 'fa-minus-circle' : 'fa-plus-circle']" />
              </button>
            </div>
          </div>
          <div v-if="Object.keys(learningmodule).length > 0 && store.state.view!='teacher'">
            <h5 class="card-title">
              {{ store.state.strings.nodes_learning_module }}
            </h5>
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
          <div v-if="editorview">
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
                <button
                  v-if="store.state.view != 'teacher'"
                  type="button"
                  class="btn btn-danger btn-sm trash-button"
                  @click="removeCourse(value.id)"
                >
                  <i
                    :class="store.state.version ? 'fa fa-trash' : 'fa fa-trash-o'"
                  />
                </button>
              </div>
            </div>
          </div>
          <div
            v-else
            class="row mb-2 mt-2"
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
              <ProgressBar :progress="0" />
            </div>
          </div>
          <div v-if="courseExpanded">
            <ExpandedCourses
              :data="data"
              @doneFolding="enableButton"
            />
          </div>
        </div>
      </div>
      <div class="card-footer">
        <OverviewRestrictionCompletion
          v-if="editorview"
          :node="data"
          :learningpath="learningpath"
        />
        <CourseCarousel
          v-if="!editorview"
          :courses="props.data"
        />
        <UserInformation
          v-if="!editorview"
          :data="data"
        />
      </div>
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

.trash-button {
  margin-left: 10px; /* Adjust margin as needed */
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
}

.non_parallel::before {
  transform: rotate(4deg);
  top: -20px;
  left: -20px;
  z-index: -1;
}

.non_parallel::after {
  transform: rotate(-4deg);
  top: -20px;
  left: -20px;
  z-index: -1;
}

</style>

