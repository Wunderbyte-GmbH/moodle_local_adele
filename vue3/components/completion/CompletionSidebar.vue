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
import { useStore } from 'vuex';

const store = useStore()
const { project, vueFlowRef, addNodes, addEdges, removeNodes} = useVueFlow()

// Reference on searchTerm
const searchTerm = ref('');
// Prev closest node
const prevClosestNode = ref(null);
// Ref to store intersecting node
const emit = defineEmits(['nodesIntersected']);
const intersectingNode = ref(null);
const nodeHeight = '250px';

const availableEdges = ['target_and', 'target_or', 'source_and', 'source_or']

// Function sets up data for nodes
function onDragStart(event, data) {
  if (event.dataTransfer) {
    event.dataTransfer.setData('application/vueflow', 'custom');
    event.dataTransfer.setData('application/data', JSON.stringify(data));
    event.dataTransfer.effectAllowed = 'move';
  }
}

// Function sets up data for nodes
function onDrag(event ) {
  //find closestNode node
  const closestNode = findClosestNode(event);
  //add drop zones to this node
  if(closestNode){
    let takenEdges = []
    props.edges.forEach((edge) => {
      if ((edge.source == closestNode.id || edge.target == closestNode.id) && !edge.id.includes('source_')
        && edge.type != 'default') {
        if(edge.source == closestNode.id ){
          takenEdges.push(edge.sourceHandle)
        } else if(edge.target == closestNode.id){
          takenEdges.push(edge.targetHandle)
        }
      }
    })
    const freeEdges = arrayDifference(availableEdges, takenEdges)

    if(freeEdges.length > 0){

      drawDropZones(freeEdges, closestNode)
      //change color of drop zone if drag position is above
      checkIntersetcion(event, closestNode)
    }
  }

  // Check if the closest node has changed
  if (closestNode !== prevClosestNode.value) {
    removeNodes(availableEdges)
    prevClosestNode.value = closestNode;
  }
}

function checkIntersetcion(event, closestNode) {
  intersectingNode.value = null;
  props.nodes.forEach((node) => {
    if(node.type.indexOf('dropzone') != -1){
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
          infotext: store.state.strings.composables_new_node,
          height: nodeHeight,
          width: '350px',
        }
      }else{
        node.data = {
          opacity: '0.6',
          bgcolor: 'grey',
          infotext: 'Drop zone',
          height: nodeHeight,
          width: '350px',
        }
      }
    }
  });
  emit('nodesIntersected', { intersecting: intersectingNode.value });
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

function drawDropZones(freeEdges, closestNode) {
  freeEdges.forEach((freeEdge) => {
    let position = {
      x: closestNode.position.x,
      y: closestNode.position.y
    }
    //draw some drop zones
    if(freeEdge == 'target_and'){
      position.y -= 350;
    }else if(freeEdge == 'source_and'){
      position.y += 350;
    }else if(freeEdge == 'source_or'){
      position.x += 450;
    }else if(freeEdge == 'target_or'){
      position.x -= 450;
    }

    if(freeEdge == 'source_and' ||(freeEdge == 'source_or' && freeEdges.includes('target_and'))){
      const data = {
        opacity: '0.6',
        bgcolor: 'grey',
        infotext: store.state.strings.completion_drop_zone,
        height: nodeHeight,
        width: '350px',
      }
      const newNode = {
        id: freeEdge,
        type: 'dropzone',
        position: position,
        label: `default node`,
        data: data
      };

      addNodes([newNode]);

      // Create an edge connecting the new drop zone node to the closest node
      let edgeData = {
        type: 'disjunctional',
        text: 'OR',
      }
      let targetHandle = 'target_or'
      if(freeEdge == 'source_and'){
        targetHandle = 'target_and'
        edgeData = {
          type: 'additional',
          text: 'AND',
        }
      }

    const newEdge = {
      id: `${closestNode.id}-${freeEdge}`,
      source: closestNode.id,
      sourceHandle: freeEdge,
      target: newNode.id,
      targetHandle: targetHandle,
      type: 'condition',
      data: edgeData,
    };
    // Add the new edge
    addEdges([newEdge]);
    }

  });
}

function arrayDifference(arr1, arr2) {
  return arr1.filter(item => !arr2.includes(item));
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
  props.nodes.forEach((node) => {
    if(node.type != 'dropzone' && node.type != 'selected'  && node.type != 'feedback'){
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
  removeNodes(availableEdges)
}

// Defined props from the parent component
const props = defineProps({
  conditions: {
    type: Array,
    default: null,
  },
  strings: {
    type: Object,
    default: null,
  },
  nodes: {
    type: Array,
    default: null,
  },
  edges: {
    type: Array,
    default: null,
  },
  type: {
    type: String,
    required: true,
  },
});

// Calculate searched courses
const filteredConditions = computed(() => {
  return props.conditions.filter(condition =>
  condition.description.toLowerCase().includes(searchTerm.value.toLowerCase().slice(1))
  );
});

</script>

<template>
  <aside
    class="col-md-4"
    style="max-width: 20% !important;"
  >
    <div type="text">
      {{ store.state.strings.completion_list_of_criteria }} {{ type }} {{ store.state.strings.completion_criteria }}
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
          v-for="condition in filteredConditions"
          :key="condition.description"
          class="vue-flow__node-input mt-1 row align-items-center justify-content-center"
          :draggable="true"
          :data="condition"
          style="width: 95%; margin-left: 0.025rem;"
          @dragstart="onDragStart($event, condition, this)"
          @drag="onDrag($event, condition)"
          @dragend="onDragEnd()"
        >
          <div
            class="col-auto info-circle"
            data-toggle="tooltip"
            data-placement="left"
            :title="condition.description"
          >
            <i class="fa fa-circle-info fa-lg" />
          </div>
          {{ condition.name }}
        </div>
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
.info-circle {
  padding: 2px;
}
</style>