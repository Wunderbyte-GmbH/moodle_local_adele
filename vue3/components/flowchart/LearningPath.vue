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

.dndflow{flex-direction:column;display:flex;height:500px}.dndflow aside{color:#fff;font-weight:700;border-right:1px solid #eee;padding:15px 10px;font-size:12px;background:rgba(16,185,129,.75);-webkit-box-shadow:0px 5px 10px 0px rgba(0,0,0,.3);box-shadow:0 5px 10px #0000004d}.dndflow aside .nodes>*{margin-bottom:10px;cursor:grab;font-weight:500;-webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);box-shadow:5px 5px 10px 2px #00000040}.dndflow aside .description{margin-bottom:10px}.dndflow .vue-flow-wrapper{flex-grow:1;height:100%}@media screen and (min-width: 640px){.dndflow{flex-direction:row}.dndflow aside{min-width:25%}}@media screen and (max-width: 639px){.dndflow aside .nodes{display:flex;flex-direction:row;gap:5px}}
.learning-path-flow.dark{background:#4e574f;}
</style>
<template>

<div class="dndflow" @drop="onDrop">
    <Modal >
    </Modal>
    <VueFlow @dragover="onDragOver" @node-drag="onNodeDrag" :default-viewport="{ zoom: 1.0 }" :class="{ dark }" class="learning-path-flow">
    <Background :pattern-color="dark ? '#FFFFFB' : '#aaa'" gap="8"/>
    <template #node-custom="{ data }">
        <CustomrNode :data="data"/>
    </template>
    </VueFlow>
    <Sidebar :courses="store.state.availablecourses" :strings="store.state.strings" />
</div>
<p>
<div class="d-flex justify-content-center">
<Controls :learninggoal="store.state.learninggoal[0]"
    @change-class="toggleClass"
/>
</div>
</p>
</template>

<script setup>
// Import needed libraries
import { ref, watch, nextTick } from 'vue'
import { MarkerType, VueFlow, useVueFlow } from '@vue-flow/core'
import { useStore } from 'vuex'
import Sidebar from './Sidebar.vue'
import Controls from './Controls.vue'
import CustomrNode from './CustomNode.vue'
import { Background } from '@vue-flow/background'
import Modal from '../modals/Modal.vue'

// Load Store and Router
const store = useStore()

// Define constants that will be referenced
const dark = ref(false)
const edgeId = ref('')


// Toggle the dark mode fi child component emits event
function toggleClass() {
    dark.value = !dark.value;
}

// generate a new id
function getId() {
    let id = nodes.value.length + 1;
    return `dndnode_${id}`
}

// load useVueFlow properties / functions
const { nodes, findNode, onConnect, addEdges, addNodes, project, vueFlowRef, removeEdges } = useVueFlow({
nodes: [],
})

// Automatically connect to node if node is close enough
function onNodeDrag(event) {
const connectionRadius = 500;
const { left, top } = vueFlowRef.value.getBoundingClientRect();
const position = project({
 x: event.event.clientX - left,
 y: event.event.clientY - top,
});
const clostestNode = findClosestNode(position, connectionRadius, event.node.id); 
if(clostestNode){
 let source = clostestNode;
 let target = event.node;
 if(source.position.y < target.position.y){
   target = clostestNode;
   source = event.node;
 }
 edgeId.value = source.id + target.id
 showPreviewConnection(source, target)
}else{
 removeEdges(edgeId.value)
}
}

// Prevent default event if node has been dropped
function onDragOver(event) {
event.preventDefault()
if (event.dataTransfer) {
 event.dataTransfer.dropEffect = 'move'
}
}

// Show a preview node if nodes are close enough
function showPreviewConnection(source, target ) {
const previewEdge = {
 id: source.id + target.id,
 source: target.id,
 target: source.id,
 sourceHandle: 'source',
 targetHandle: 'target',
 animated: true,
 style: {
   'stroke-width': 5,
 },
 markerEnd: MarkerType.ArrowClosed,
};
removeEdges(target.id + source.id)
addEdges([previewEdge]);
}

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
params.animated = true;
params.style = {
 'stroke-width': 5, 
};
params.markerEnd = MarkerType.ArrowClosed;
if (params.source !== store.state.startnode) {
 // Swap source and target positions
 params.target = params.source;
 params.source = store.state.startnode;
}
params.id = params.source + params.target
addEdges(params);
}

// Triggers handle connect 
onConnect(handleConnect);

// Adding setting up nodes and potentional edges
function onDrop(event) {
const type = event.dataTransfer?.getData('application/vueflow')
const data = JSON.parse(event.dataTransfer?.getData('application/data'));
const { left, top } = vueFlowRef.value.getBoundingClientRect()

const position = project({
 x: event.clientX - left,
 y: event.clientY - top,
})

const id = getId();
data.node_id = id;

const newNode = {
 id: id,
 type,
 position,
 label: `${type} node`,
 data: data
}
addNodes([newNode])
removeEdges('preview_edge')
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
}
</script>