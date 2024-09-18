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
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useVueFlow } from '@vue-flow/core'
import drawDropzone from '../../composables/nodesHelper/drawDropzone'
import formatIntersetingNodes from '../../composables/nodesHelper/formatIntersetingNodes'
import LearningModule from './LearningModule.vue'
import { useStore } from 'vuex';
const store = useStore()
const { project, vueFlowRef, findNode, nodes, addNodes, removeNodes, addEdges } = useVueFlow()

// Reference on searchTerm
const searchTerm = ref('');

// Reference on searchTerm
const activeNode = ref('');

// Ref to store intersecting node
const emit = defineEmits([
  'nodesIntersected',
  'changedModule',
]);

const intersectingNode = ref(null);
const closestNode = ref({})

// Prev closest node
const prevClosestNode = ref(null);

const activeTab = ref(0);

// Defined props from the parent component
const props = defineProps({
  learningmodule: {
    type: Object,
    required: true,
  },
  courses: {
    type: Array,
    required: true,
  },
  strings: {
    type: Object,
    required: true,
  }
});

const tabInactiveColor = props.strings.LIGHT_GRAY
const tabActiveColor = props.strings.LIGHT_SEA_GREEN
// Function sets up data for nodes
function onDragStart(event, data) {
  if (event.dataTransfer) {
    event.dataTransfer.setData('application/vueflow', 'custom');
    event.dataTransfer.setData('application/data', JSON.stringify(data));
    event.dataTransfer.effectAllowed = 'move';
  }
}

// Calculate searched courses
const filteredCourses = computed(() => {
  if (props.courses) {
    if(searchTerm.value.toLowerCase().startsWith('#')){
      return props.courses.filter(course =>
        course.tags.toLowerCase().includes(searchTerm.value.toLowerCase().slice(1))
      );
    }
    return props.courses.filter(course =>
      course.fullname.toLowerCase().includes(searchTerm.value.toLowerCase())
    );
  }
  return []
});

// Function sets up data for nodes
function onDrag(event) {
  //find closestNode node
  const startingNode = findNode('starting_node');
  const dz_check_node = findNode('dropzone_parent');
  if (dz_check_node == undefined) {
    closestNode.value = findClosestNode(event);
  } else if (!checkDistance(event, closestNode.value)) {
    activeNode.value = null
    closestNode.value = null
  }
  //add drop zones to this node
  let startingNodeIntersecting = checkIntersetcion(event, startingNode)
  if(closestNode.value && startingNodeIntersecting){
    if (dropzoneShown()) {
      activeNode.value = closestNode.value
      const newDrop = drawDropzone(closestNode.value, store)
      addNodes(newDrop.nodes);
      addEdges(newDrop.edges);
    }
    checkIntersetcion(event, closestNode.value)
  }else{
    activeNode.value = null
    removeNodes(['dropzone_parent', 'dropzone_child', 'dropzone_and', 'dropzone_or'])
  }

  // Check if the closest node has changed
  if (closestNode.value !== prevClosestNode.value && (dropzoneShown() ||aboveDistance())) {
    activeNode.value = null
    removeNodes(['dropzone_parent', 'dropzone_child', 'dropzone_and', 'dropzone_or'])
    prevClosestNode.value = closestNode.value;
  }
}

function checkDistance(event, closestNode) {
  if (closestNode) {
    const { left, top } = vueFlowRef.value.getBoundingClientRect();
    let event_clientX = event.clientX
    let event_clientY = event.clientY
    if (
      event_clientX == 0 &&
      event_clientY == 0
    ){
      event_clientX = mousePosition.value.x
      event_clientY = mousePosition.value.y
    }
    const position_mouse = project({
      x: event_clientX - left,
      y: event_clientY - top,
    });
    const position_node = {
      x: closestNode.position.x + closestNode.dimensions.width/2,
      y: closestNode.position.y + closestNode.dimensions.height/2,
    };
    if (Math.sqrt(
      Math.pow(position_mouse.x - position_node.x, 2) +
      Math.pow(position_mouse.y - position_node.y, 2))
      < 550
    ) {
      return true
    }
  }
  return false
}

function checkIntersetcion(event, closestNode) {
  let insideStartingNode = false;
  let event_clientX = event.clientX
  let event_clientY = event.clientY
  if (
    event_clientX == 0 &&
    event_clientY == 0
  ){
    event_clientX = mousePosition.value.x
    event_clientY = mousePosition.value.y
  }
  intersectingNode.value = null;
  nodes.value.forEach((node) => {
    if(node.type == 'dropzone' ||node.type == 'conditionaldropzone'){
      const { left, top } = vueFlowRef.value.getBoundingClientRect();
      const position = project({
        x: event_clientX - left,
        y: event_clientY - top,
      });
      const nodesIntersecting = areNodesIntersecting(position, node)
      const nodeFormat = formatIntersetingNodes(
        nodesIntersecting, node, intersectingNode.value,
        closestNode, insideStartingNode, store)
      node = nodeFormat.node
      intersectingNode.value = nodeFormat.intersectingNode
      insideStartingNode = nodeFormat.insideStartingNode
    }
  });
  emit('nodesIntersected', { intersecting: intersectingNode.value });
  return insideStartingNode
}

function aboveDistance() {
  if (activeNode.value != null) {
    const { left, top } = vueFlowRef.value.getBoundingClientRect();
    let event_clientX = event.clientX
    let event_clientY = event.clientY
    if (
      event_clientX == 0 &&
      event_clientY == 0
    ){
      event_clientX = mousePosition.value.x
      event_clientY = mousePosition.value.y
    }
    const position = project({
      x: event_clientX - left,
      y: event_clientY - top,
    });

    const middleNode = {
      x: activeNode.value.position.x + activeNode.value.dimensions.width/2,
      y: activeNode.value.position.y + activeNode.value.dimensions.height/2,
    };

    if (Math.sqrt(Math.pow(position.x - middleNode.x, 2) + Math.pow(position.y - middleNode.y, 2)) > 500) {
      return true
    }
  }
  return false
}

function dropzoneShown() {
  if (findNode('dropzone_or')){
    return false;
  }
  return true
}

function onChangedModule(learningpath) {
  emit('changedModule', learningpath)
}

// Function to check if two nodes intersect
function areNodesIntersecting(position, node) {
  return (
    position.x < node.position.x + node.dimensions.width &&
    position.x > node.position.x &&
    position.y < node.position.y + node.dimensions.height &&
    position.y > node.position.y
  );
}
const mousePosition = ref({ x: 0, y: 0 });

function updateMousePosition(event) {
  mousePosition.value = { x: event.clientX, y: event.clientY };
}

onMounted(() => {
  document.addEventListener('dragover', updateMousePosition);
});

onBeforeUnmount(() => {
  document.removeEventListener('dragover', updateMousePosition);
});

// Find the closest node within a set boundary
function findClosestNode(event) {
  const connectionRadius = 550;
  const { left, top } = vueFlowRef.value.getBoundingClientRect();
  let event_clientX = event.clientX
  let event_clientY = event.clientY
  if (
    event_clientX == 0 &&
    event_clientY == 0
  ){
    event_clientX = mousePosition.value.x
    event_clientY = mousePosition.value.y
  }

  const position = project({
    x: event_clientX - left,
    y: event_clientY - top,
  });
  let closestNode = null;
  let closestDistance = Infinity;

  nodes.value.forEach((node) => {
    if(node.type != 'dropzone' && node.type != 'conditionaldropzone'){
      const nodeCenter = {
        x: node.position.x + node.dimensions.width / 2,
        y: node.position.y + node.dimensions.height / 2
      };
      const distance = Math.sqrt(
        Math.pow(position.x - nodeCenter.x, 2) +
        Math.pow(position.y - nodeCenter.y, 2)
      );
      if (distance < closestDistance && distance < connectionRadius &&
      Math.abs(position.y - nodeCenter.y) < 360) {
        closestDistance = distance;
        closestNode = node;
      }
    }
  });
  return closestNode;
}

function onDragEnd(){
  removeNodes(['dropzone_parent', 'dropzone_child', 'dropzone_and', 'dropzone_or'])
}

function changeTab(index) {
  activeTab.value = index;
}

</script>

<template>
  <aside
    class="col-md-2"
    style="max-width: 20% !important;"
  >
    <div class="nav nav-tabs">
      <div
        class="nav-item"
      >
        <a
          :class="['nav-link', { 'active': activeTab === 0 }]"
          :style="{ backgroundColor: activeTab === 0 ? tabActiveColor : tabInactiveColor }"
          @click="changeTab(0)"
        >
          {{ store.state.strings.flowchart_courses }}
        </a>
      </div>
      <div
        class="nav-item"
      >
        <a
          :class="['nav-link', { 'active': activeTab === 1 }]"
          :style="{ backgroundColor: activeTab === 1 ? tabActiveColor : tabInactiveColor }"
          @click="changeTab(1)"
        >
          {{ store.state.strings.flowchart_learning_package }}
        </a>
      </div>
    </div>
    <div
      v-if="!activeTab"
    >
      <div
        class="col long-text"
        type="text"
      >
        {{ strings.fromavailablecourses }}
      </div>
      <div
        class="col long-text"
        type="text"
      >
        {{ strings.tagsearch_description }}
      </div>
      <div
        class="row ml-2 long-text"
        type="text"
      >
        <input
          id="searchTerm"
          v-model="searchTerm"
          class="form-control"
          :placeholder="strings.placeholder_search"
        >
      </div>
      <div class="learning-path-nodes-container">
        <div class="nodes">
          <div
            v-for="course in filteredCourses"
            :key="course.id"
            class="vue-flow__node-input mt-1 row align-items-center justify-content-center"
            :draggable="true"
            :data="course"
            style="width: 95%; padding-left: 1rem; margin-left: 0.025rem; height: 3rem"
            @dragstart="onDragStart($event, course)"
            @drag="onDrag($event)"
            @dragend="onDragEnd()"
          >
            <div
              class="col-auto"
              data-toggle="tooltip"
              data-placement="left"
              :title="store.state.strings.flowchart_hover_darg_drop"
            >
              <i class="fa fa-info-circle fa-lg" />
            </div>
            <div class="col long-text">
              {{ course.fullname }}
            </div>
            <div
              class="col-auto"
              data-toggle="tooltip"
              data-placement="right"
              :title="store.state.strings.flowchart_hover_click_here"
            >
              <a
                :href="store.state.wwwroot + '/course/view.php?id=' + course.course_node_id[0]"
                target="_blank"
              >
                <i class="fa fa-link" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div v-else>
      <LearningModule
        :learningmodule="learningmodule"
        :strings="strings"
        @changed-module="onChangedModule($event, learningpath)"
      />
    </div>
  </aside>
</template>

<style scoped>
.long-text {
  padding-left: 0 !important;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.form-control{
  width: 90%;
}
.learning-path-nodes-container {
  margin-top: 20px;
  max-height: 440px;
  overflow-y: auto;
}
.nav-item{
  margin-right: 2px;
  max-width: 50%;
}
.nav-tabs {
  display: flex !important;
  flex-wrap: nowrap !important;
  border-bottom: 1px solid #e0e0e0; /* Light gray bottom border */
}
.nav-link {
  display: block;
  white-space: nowrap; /* Keeps the text on a single line */
  overflow: hidden; /* Hides text that overflows the element's box */
  text-overflow: ellipsis; /* Adds an ellipsis to signify hidden text */
  padding: 0.5rem 1rem; /* Adjust padding as needed */
  color: #555555c7;
}

.nav-link.active {
  color: #fff; /* White text color for active tab */
}

</style>