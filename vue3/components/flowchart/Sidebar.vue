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
import { defineProps, ref, computed } from 'vue';
import { useVueFlow } from '@vue-flow/core'
const { project, vueFlowRef, findNode, nodes, addNodes, removeNodes, addEdges } = useVueFlow()

// Reference on searchTerm
const searchTerm = ref('');

// Ref to store intersecting node
const emit = defineEmits();
const intersectingNode = ref(null);

// Prev closest node
const prevClosestNode = ref(null);

// Defined props from the parent component
const props = defineProps({
  courses: Array,
  strings: Object,
  require: true,
});

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
    drawDropZones(closestNode)
    checkIntersetcion(event, closestNode)
  }else{
    removeNodes(['dropzone'])
  }

  // Check if the closest node has changed
  if (closestNode !== prevClosestNode.value) {
    removeNodes(['dropzone_parent', 'dropzone_child'])
    prevClosestNode.value = closestNode;
  }
}

function drawDropZones(closestNode) {
  const dropZoneCourseNodes = {
    parent: {
      name: ' Parent',
      positionY: -250,
      positionX: 0,
    },
    child: {
      name: ' Child',
      positionY: 250,
      positionX: 0,
    },
  }
  let data = {
    opacity: '0.6',
    bgcolor: 'grey',
    infotext: 'Drop zone',
    height: '200px',
    width: '400px',
  }

  //check if closest node has childerns TODO   
  for (const key in dropZoneCourseNodes){
    data.infotext += dropZoneCourseNodes[key].name 

    let position = {
      x: getOffsetX(closestNode, key), 
      y: closestNode.position.y + dropZoneCourseNodes[key].positionY
    }
    const newNode = {
      id: 'dropzone_' + key,
      type: 'dropzone',
      position: position,
      label: `default node`,
      data: data
    }
    addNodes([newNode]);

    let targetHandle = 'source_and'
    let sourceHandle =  'target'

    if(key == 'child'){
      targetHandle = 'target_and'
      sourceHandle =  'source'
    }

    const newEdge = {
      id: `${closestNode.id}-${key}`,
      source: closestNode.id,
      sourceHandle: sourceHandle,
      target: newNode.id,
      targetHandle: targetHandle,
      type: 'default',
    };
    // Add the new edge
    addEdges([newEdge]);
  }
}

function getOffsetX(closestNode, relation){
  let relationHandle = closestNode.childCourse
  if(relation == 'parent'){
    relationHandle = closestNode.parentCourse
  }

  if(relationHandle.length == 0 ||
  relationHandle.indexOf('starting_node') != -1){
    return closestNode.position.x
  }

  return closestNode.position.x + 500

}

function checkIntersetcion(event, closestNode) {
  let insideStartingNode = false;
  intersectingNode.value = null;
  nodes.value.forEach((node) => {
    if(node.type == 'dropzone'){
      const { left, top } = vueFlowRef.value.getBoundingClientRect();
      const position = project({
        x: event.clientX - left,
        y: event.clientY - top,
      });
      const nodesIntersecting = areNodesIntersecting(position, node)
      if(nodesIntersecting){
        intersectingNode.value = { closestnode: closestNode, dropzone: node};
        node.data = {
          opacity: '0.75',
          bgcolor: 'chartreuse',
          infotext: 'Drop to connect here',
          height: '200px',
          width: '400px',
        }
      }else{
        node.data = {
          opacity: '0.6',
          bgcolor: 'grey',
          infotext: 'New Staring node',
          height: '200px',
          width: '400px',
        }
        if(node.id == 'dropzone_parent'){
          node.data.infotext = 'Drop zone Parent'
        }else if(node.id == 'dropzone_child'){
          node.data.infotext = 'Drop zone Child'
        }else {
          insideStartingNode = true;
        }
      }
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
    if(node.type != 'dropzone'){

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
  removeNodes(['dropzone_parent', 'dropzone_child'])
}

</script>

<template>
  <aside class="col-md-2" style="min-width: 10% !important;"> <!-- Adjust the width as needed -->
    <div type="text">{{ strings.fromavailablecourses }}</div>
    <div type="text">{{ strings.tagsearch_description }}</div>
    <input class="form-control" v-model="searchTerm" :placeholder="strings.placeholder_search" />
    <div class="learning-path-nodes-container">
      <div class="nodes">
        <template v-for="course in filteredCourses" :key="course.id">
          <div class="vue-flow__node-input mt-1" :draggable="true" 
            @dragstart="onDragStart($event, course)" 
            @drag="onDrag($event)"
            @dragend="onDragEnd()"
            :data="course" style="width: 100%;">
            {{ course.fullname }}
          </div>
        </template>
      </div>
    </div>
</aside>
</template>

<style scoped>
.learning-path-nodes-container {
  margin-top: 20px;
  height: 80%;
  overflow-y: auto;
}
</style>