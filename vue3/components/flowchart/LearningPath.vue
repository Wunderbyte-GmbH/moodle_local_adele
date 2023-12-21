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

 <style scoped>
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/theme-default.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';

.dndflow{flex-direction:column;display:flex;height:600px}.dndflow aside{color:#fff;font-weight:700;border-right:1px solid #eee;padding:15px 10px;font-size:12px;background:rgba(16,185,129,.75);-webkit-box-shadow:0px 5px 10px 0px rgba(0,0,0,.3);box-shadow:0 5px 10px #0000004d}.dndflow aside .nodes>*{margin-bottom:10px;cursor:grab;font-weight:500;-webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);box-shadow:5px 5px 10px 2px #00000040}.dndflow aside .description{margin-bottom:10px}.dndflow .vue-flow-wrapper{flex-grow:1;height:100%}@media screen and (min-width: 640px){.dndflow{flex-direction:row}.dndflow aside{min-width:25%}}@media screen and (max-width: 639px){.dndflow aside .nodes{display:flex;flex-direction:row;gap:5px}}
.learning-path-flow.dark{background:#4e574f;}
</style>
<template>

<div class="dndflow" @drop="onDrop">
    <Modal >
    </Modal>
    <VueFlow @dragover="onDragOver" :default-viewport="{ zoom: 1.0, x: 0, y: 0 }" 
      :class="{ dark }" :fit-view-on-init="true" :max-zoom="3" :min-zoom="0.3"
      class="learning-path-flow">
      <Background :pattern-color="dark ? '#FFFFFB' : '#aaa'" gap="8"/>
      <template #node-custom="{ data }">
          <CustomNode :data="data"/>
      </template>
      <template #node-dropzone="{ data }">
          <DropzoneNode :data="data"/>
      </template>
      <MiniMap nodeColor="grey"/>
    </VueFlow>
    <Sidebar 
      @nodesIntersected="handleNodesIntersected"
      :courses="store.state.availablecourses" 
      :strings="store.state.strings" />
</div>
<p>
<div class="d-flex justify-content-center">
<Controls :learninggoal="store.state.learninggoal[0]"
    @change-class="toggleClass"
/>
</div>
</p>

<p>
  <UserList :learningPathId = "store.state.learninggoal[0]"/>
</p>

</template>

<script setup>
// Import needed libraries
import { ref, watch, nextTick } from 'vue'
import { VueFlow, useVueFlow } from '@vue-flow/core'
import { useStore } from 'vuex'
import Sidebar from './Sidebar.vue'
import Controls from './Controls.vue'
import CustomNode from '../nodes/CustomNode.vue'
import { Background } from '@vue-flow/background'
import Modal from '../modals/Modal.vue'
import { MiniMap } from '@vue-flow/minimap'
import getNodeId from '../../composables/getNodeId'
import DropzoneNode from '../nodes/DropzoneNode.vue'
import { notify } from "@kyvg/vue3-notification"
import shiftNodesDown from '../../composables/shiftNodesDown'
import setStartingNode from '../../composables/setStartingNode';
import UserList from '../user_view/UserList.vue'
import addCustomEdge from '../../composables/addCustomEdge';
import removeDropzones from '../../composables/removeDropzones';

// Load Store and Router
const store = useStore()

// Define constants that will be referenced
const dark = ref(false)
const edgeId = ref('')
// Intersected node
const intersectedNode = ref(null);

// Toggle the dark mode fi child component emits event
function toggleClass() {
    dark.value = !dark.value;
}

// load useVueFlow properties / functions
const { nodes, findNode, onConnect, addEdges, 
    addNodes, removeNodes,
    toObject, fitView } = useVueFlow({
nodes: [],
})

// Prevent default event if node has been dropped
function handleNodesIntersected({ intersecting }) {
  intersectedNode.value = intersecting
}

// Automatically connect to node if node is close enough
// function onNodeDrag(event) {
//   const connectionRadius = 500;
//   const { left, top } = vueFlowRef.value.getBoundingClientRect();
//   const position = project({
//   x: event.event.clientX - left,
//   y: event.event.clientY - top,
//   });
//   const clostestNode = findClosestNode(position, connectionRadius, event.node.id); 
//   if(clostestNode){
//   let source = clostestNode;
//   let target = event.node;
//   if(source.position.y < target.position.y){
//     target = clostestNode;
//     source = event.node;
//   }
//   edgeId.value = source.id + target.id
//   showPreviewConnection(source, target)
//   }else{
//   removeEdges(edgeId.value)
//   }
// }

// Prevent default event if node has been dropped
function onDragOver(event) {
  event.preventDefault()
  if (event.dataTransfer) {
  event.dataTransfer.dropEffect = 'move'
  }
}

// Show a preview node if nodes are close enough
// function showPreviewConnection(source, target ) {
//   removeEdges(target.id + source.id)
//   addEdges(addCustomEdge(source.id, target.id));
// }

// Find the closest node within a set boundary
function findClosestNode(position, connectionRadius, draggedId) {
let closestNode = null;
let closestDistance = Infinity;

nodes.value.forEach((node) => {
 const distance = Math.sqrt(
   Math.pow(position.x - node.position.x, 2) +
   Math.pow(position.y - node.position.y, 2)
 );

 if (node.id != draggedId && distance < closestDistance && distance < connectionRadius) {
   closestDistance = distance;
   closestNode = node;
 }
});

return closestNode;
}

// Adjust and add edges if connection was made
function handleConnect(params) {
if (params.source !== store.state.startnode) {
 // Swap source and target positions
 params.target = params.source;
 params.source = store.state.startnode;
}
addEdges(addCustomEdge( params.target, params.source));
}

// Triggers handle connect 
onConnect(handleConnect);

// Adding setting up nodes and potentional edges
function onDrop(event) {
  if(intersectedNode.value){
    const type = event.dataTransfer?.getData('application/vueflow')
    const data = JSON.parse(event.dataTransfer?.getData('application/data'));

    const position = {
      x: intersectedNode.value.dropzone.position.x + intersectedNode.value.dropzone.dimensions.width/2,
      y: intersectedNode.value.dropzone.position.y + intersectedNode.value.dropzone.dimensions.height/2,
    }

    const id = getNodeId('dndnode_', nodes.value);
    data.node_id = id;

    //if is starting node dz
    let parentCourse = []
    let childCourse = []
    if(intersectedNode.value.closestnode.id == 'starting_node'){
      parentCourse.push('starting_node')
    }
    else if (intersectedNode.value.dropzone.id == 'dropzone_parent'){
        childCourse.push(intersectedNode.value.closestnode.id)
        parentCourse.push('starting_node')
        intersectedNode.value.closestnode.parentCourse.push(data.node_id)
        // Check if the array contains the value
        const index = intersectedNode.value.closestnode.parentCourse.indexOf('starting_node');
        // If the value is found, remove it
        if (index !== -1) {
          intersectedNode.value.closestnode.parentCourse.splice(index, 1)
          shiftNodesDown(data.node_id, nodes.value)
        }
        position.y = intersectedNode.value.dropzone.dimensions.height/2
    }else if(intersectedNode.value.dropzone.id == 'dropzone_child'){
      parentCourse.push(intersectedNode.value.closestnode.id)
      intersectedNode.value.closestnode.childCourse.push(data.node_id)
      position.y += 300
    }

    if (intersectedNode.value.closestnode.position.x < intersectedNode.value.dropzone.position.x) {
      position.x += intersectedNode.value.closestnode.dimensions.width
    }


    const newNode = {
      id: id,
      type,
      position,
      label: `${type} node`,
      data: data,
      draggable: true,
      parentCourse: parentCourse,
      childCourse: childCourse,
    }
    addNodes([newNode])

    if(intersectedNode.value.closestnode.id == 'starting_node'){
      setStartingNode(removeNodes, nextTick, addNodes, nodes.value)
    }

    // align node position after drop, so it's centered to the mouse
    nextTick(() => {
    const node = findNode(newNode.id)
    const stop = watch(
      () => node.dimensions,
      (dimensions) => {
        if (dimensions.width > 0 && dimensions.height > 0) {
          node.position = { x: Math.round((node.position.x - node.dimensions.width / 2) * 10)/10, y:  Math.round((node.position.y - node.dimensions.height / 2) * 10)/10 }
          stop()
        }
      },
      { deep: true, flush: 'post' },
    )
    })
    if(intersectedNode.value.dropzone.id.includes('dropzone_')){
      let source = intersectedNode.value.closestnode.id  
      let target = newNode.id

      if(intersectedNode.value.dropzone.id.includes('child')){
        source = newNode.id 
        target = intersectedNode.value.closestnode.id 
      }
      // Add the new edge
      addEdges(addCustomEdge(source, target));
    }
    let tree = toObject()
    tree = removeDropzones(tree)
    store.state.learninggoal[0].json = {
      tree: tree,
    }
  } else{
    notify({
      title: 'Node drop refused',
      text: 'Please drop the node in the dropzones, which will be shown if you drag a node to an exsisting node.',
      type: 'warn'
    });
  }
}

// Watch for changes in the nodes
watch(
  () => nodes.value,
  () => {
    setTimeout(() => {
      fitView({ duration: 1000, padding: 0.5 });
    }, 100);
  },
  { deep: true } // Enable deep watching to capture changes in nested properties
);
watch(
  () => nodes.value.length,
  (newNodes, oldNodes) => {
    if(oldNodes > newNodes){
      setStartingNode(removeNodes, nextTick, addNodes, nodes.value, true)
    }
  },
);

</script>