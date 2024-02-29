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
import { ref, computed } from 'vue';
import { useVueFlow } from '@vue-flow/core'
import drawDropzone from '../../composables/nodesHelper/drawDropzone'
import formatIntersetingNodes from '../../composables/nodesHelper/formatIntersetingNodes'
import LearningModule from './LearningModule.vue'
const { project, vueFlowRef, findNode, nodes, addNodes, removeNodes, addEdges } = useVueFlow()

// Reference on searchTerm
const searchTerm = ref('');

// Ref to store intersecting node
const emit = defineEmits(['nodesIntersected']);
const intersectingNode = ref(null);

// Prev closest node
const prevClosestNode = ref(null);

const activeTab = ref(0);

// Defined props from the parent component
const props = defineProps({
  learningmodule: {
    type: Array,
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
  if(searchTerm.value.toLowerCase().startsWith('#')){
    return props.courses.filter(course =>
      course.tags.toLowerCase().includes(searchTerm.value.toLowerCase().slice(1))
    );
  }
  return props.courses.filter(course =>
    course.fullname.toLowerCase().includes(searchTerm.value.toLowerCase())
  );
});

// Function sets up data for nodes
function onDrag(event) {
  //find closestNode node
  const startingNode = findNode('starting_node'); 
  const closestNode = findClosestNode(event); 
  
  //add drop zones to this node 
  let startingNodeIntersecting = checkIntersetcion(event, startingNode)
  if(closestNode && startingNodeIntersecting){
    const newDrop = drawDropzone(closestNode)
    addNodes(newDrop.nodes);
    addEdges(newDrop.edges);
    checkIntersetcion(event, closestNode)
  }else{
    removeNodes(['dropzone', 'dropzone_and', 'dropzone_or'])
  }

  // Check if the closest node has changed
  if (closestNode !== prevClosestNode.value) {
    removeNodes(['dropzone_parent', 'dropzone_child', 'dropzone_and', 'dropzone_or'])
    prevClosestNode.value = closestNode;
  }
}

function checkIntersetcion(event, closestNode) {
  let insideStartingNode = false;
  intersectingNode.value = null;
  nodes.value.forEach((node) => {
    if(node.type == 'dropzone' ||node.type == 'conditionaldropzone'){
      const { left, top } = vueFlowRef.value.getBoundingClientRect();
      const position = project({
        x: event.clientX - left,
        y: event.clientY - top,
      });
      const nodesIntersecting = areNodesIntersecting(position, node)
      const nodeFormat = formatIntersetingNodes(nodesIntersecting, node, intersectingNode.value,
        closestNode, insideStartingNode)
      node = nodeFormat.node
      intersectingNode.value = nodeFormat.intersectingNode
      insideStartingNode = nodeFormat.insideStartingNode

    }
  });
  emit('nodesIntersected', { intersecting: intersectingNode.value });
  return insideStartingNode
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

// Find the closest node within a set boundary
function findClosestNode(event) {
  const connectionRadius = 800;
  const { left, top } = vueFlowRef.value.getBoundingClientRect();
  const position = project({
    x: event.clientX - left,
    y: event.clientY - top,
  });
  let closestNode = null;
  let closestDistance = Infinity;


  nodes.value.forEach((node) => {
    if(node.type != 'dropzone' && node.type != 'conditionaldropzone'){
      const distance = Math.sqrt(
        Math.pow(position.x - node.position.x, 2) +
        Math.pow(position.y - node.position.y, 2)
      );
      if (distance < closestDistance && distance < connectionRadius) {
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
          Courses
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
          Learning package
        </a>
      </div>
    </div>

    <div v-if="!activeTab">
      <div type="text">
        {{ strings.fromavailablecourses }}
      </div>
      <div type="text">
        {{ strings.tagsearch_description }}
      </div>
      <input 
        id="searchTerm" 
        v-model="searchTerm" 
        class="form-control" 
        :placeholder="strings.placeholder_search" 
      >
      <div class="learning-path-nodes-container">
        <div class="nodes">
          <div 
            v-for="course in filteredCourses" 
            :key="course.id"
            class="vue-flow__node-input mt-1 row align-items-center justify-content-center"
            :draggable="true" 
            :data="course" 
            style="width: 95%; padding-left: 1rem; margin-left: 0.025rem;"
            @dragstart="onDragStart($event, course)" 
            @drag="onDrag($event)"
            @dragend="onDragEnd()"
          >
            <div 
              class="col-auto"
              data-toggle="tooltip" 
              data-placement="left" 
              title="Drag and drop the course inside the drop zones to include it in the learning path." 
            >
              <i class="fa-solid fa-circle-info fa-lg" />
            </div>
            {{ course.fullname }}
            <div 
              class="col-auto"
              data-toggle="tooltip" 
              data-placement="right" 
              title="Click here to go to course" 
            >
              <a 
                :href="'/course/view.php?id=' + course.course_node_id[0]" 
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
      />
    </div>
  </aside>
</template>

<style scoped>
.learning-path-nodes-container {
  margin-top: 20px;
  height: 80%;
  overflow-y: auto;
}
.nav-item{
  margin-right: 2px;
}
.nav-tabs {
  display: flex !important;
  flex-wrap: nowrap !important;
  border-bottom: 1px solid #e0e0e0; /* Light gray bottom border */
}
.nav-link {
  padding: 0.5rem 1rem;
  color: #555555c7; /* Dark gray text color */
}

.nav-link.active {
  color: #fff; /* White text color for active tab */
}

</style>