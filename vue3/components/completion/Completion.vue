<template>
    <h3>Edit Completion of course node</h3>
    <div v-if="completions !== null">
        <div class="dndflowcompletion" @drop="onDrop">
            <VueFlow @dragover="onDragOver" :default-viewport="{ zoom: 1.0 }" class="completions">
                <Background :pattern-color="'#FFFFFF'" gap="8" bgColor="black" />
                <template #node-custom="{ data }">
                    <CompletionNode :data="data"/>
                </template>
                <template #node-dropzone="{ }">
                    <DropzoneNode />
                </template>
                <template #node-selected="props">
                  <DropzoneSelectedNode />
                </template>
                <template #edge-additional="props">
                  <AdditionalEgde v-bind="props" />
                </template>
                <template #edge-disjunctional="props">
                  <DisjunctionalEgde v-bind="props" />
                </template>
                <MiniMap />
            </VueFlow>
            <Sidebar :completions="completions" 
              :strings="store.state.strings" 
              :nodes = nodes
              :edges = edges
              @nodesIntersected="handleNodesIntersected" />
        </div>
          <div class="d-flex justify-content-center">
            <Controls />
          </div>
    </div>
    <div v-else>
        Loading completion...
    </div>
</template>
<script setup>
// Import needed libraries
import { ref, onMounted, nextTick, watch, onUpdated } from 'vue';
import { useStore } from 'vuex';
import {  VueFlow, useVueFlow } from '@vue-flow/core'
import Sidebar from './CompletionSidebar.vue'
import { Background } from '@vue-flow/background'
import Controls from './CompletionControls.vue'
import CompletionNode from './CompletionNode.vue'
import DropzoneNode from './DropzoneNode.vue'
import DropzoneSelectedNode from './DropzoneNodeIntersected.vue'
import { notify } from "@kyvg/vue3-notification"
import AdditionalEgde from './AndCompletionLine.vue'
import DisjunctionalEgde from './OrCompletionLine.vue'
import { MiniMap } from '@vue-flow/minimap'

const { nodes, edges, addNodes, project, vueFlowRef, fitView, onConnect, addEdges } = useVueFlow({
  nodes: [],})

// Load Store 
const store = useStore();

// Get all available completions
const completions = ref(null);

// Intersected node
const intersectedNode = ref(null);

onMounted(async () => {
    try {
        completions.value = await store.dispatch('fetchCompletions');
    } catch (error) {
        console.error('Error fetching completions:', error);
    }
});

// Prevent default event if node has been dropped
function handleNodesIntersected({ intersecting }) {
  intersectedNode.value = intersecting
}

// Prevent default event if node has been dropped
function onDragOver(event) {
  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}

// Adding setting up nodes and potentional edges
function onDrop(event) {
  if(nodes.value.length == 0 || intersectedNode.value){
    const type = event.dataTransfer?.getData('application/vueflow')
    const data = JSON.parse(event.dataTransfer?.getData('application/data'));
    const { left, top } = vueFlowRef.value.getBoundingClientRect()

    let position = project({
      x: event.clientX - left,
      y: event.clientY - top,
    })
    if(intersectedNode.value){
      position.x = intersectedNode.value.dropzone.position.x;
      position.y = intersectedNode.value.dropzone.position.y;
    }

    const id = getId()
    data.node_id = id

    const newNode = {
      id: id,
      type,
      position: { x: position.x , y: position.y },
      label: `${type} node`,
      data: data,
      draggable: false,
    };

    addNodes([newNode]);
    if(intersectedNode.value){
      // Create an edge connecting the new drop zone node to the closest node
      let targetHandle = 'target_or'
      let type = 'disjunctional'
      if(intersectedNode.value.dropzone.id == 'source_and'){
        targetHandle = 'target_and'
        type = 'additional'
      }
      const newEdge = {
        id: intersectedNode.value.closestnode.id  + '-' + newNode.id,
        source: intersectedNode.value.closestnode.id,
        sourceHandle: intersectedNode.value.dropzone.id,
        target: newNode.id,
        targetHandle: targetHandle,
        type: type
      };
      // Add the new edge
    addEdges([newEdge]);
    }
    } else{
    notify({
      title: 'Node drop refused',
      text: 'Please drop the node in the dropzones, which will be shown if you drag a node to an exsisting node.',
      type: 'warn'
    });
  }
}
// generate a new id
function getId() {
  let highestId = 1

  //get target nodes position and targets node sources
  
  nodes.value.forEach((node) => {
    if (node.id.includes('condition_')) {
      const currentId = Number(node.id.slice(node.id.indexOf('_') + 1));
      if(highestId <= currentId){
        highestId = currentId +1
      }
    }
  })
  return `condition_${highestId}`
}

// Adjust and add edges if connection was made
function handleConnection(params) {
  params.type = 'custom'
  addEdges(params);
}

// Triggers handle connect 
onConnect(handleConnection);

</script>

<style scoped>
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/theme-default.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';

.dndflowcompletion{flex-direction:column;display:flex;height:500px}.dndflowcompletion aside{color:#fff;font-weight:700;border-right:1px solid #eee;padding:15px 10px;font-size:12px;background:rgba(16,185,129,.75);-webkit-box-shadow:0px 5px 10px 0px rgba(0,0,0,.3);box-shadow:0 5px 10px #0000004d}.dndflowcompletion aside .nodes>*{margin-bottom:10px;cursor:grab;font-weight:500;-webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);box-shadow:5px 5px 10px 2px #00000040}.dndflowcompletion aside .description{margin-bottom:10px}.dndflowcompletion .vue-flow-wrapper{flex-grow:1;height:100%}@media screen and (min-width: 640px){.dndflowcompletion{flex-direction:row}.dndflowcompletion aside{min-width:25%}}@media screen and (max-width: 639px){.dndflowcompletion aside .nodes{display:flex;flex-direction:row;gap:5px}}
.learning-path-flow{background:#4e574f;}
.vue-flow__node.intersecting{background-color:#ff0}

</style>