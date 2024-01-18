<template>
  <div>
    <button 
      class="btn btn-outline-primary"
      @click="goBack" 
    >
      <i class="fa fa-arrow-left" /> Go Back to Learningpath
    </button>
  
    <h3>Edit Completion criteria of course node</h3>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">
          <i class="fa fa-check-circle" /> Completion Criteria for:
        </h5>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">
            <i class="fa fa-header" /> Course Title: {{ store.state.node.fullname }}
          </li>
          <li class="list-group-item">
            <i class="fa fa-tag" /> Tags: {{ store.state.node.tags }}
          </li>
        </ul>
      </div>
      <div v-if="completions !== null">
        <ParentNodes :parent-nodes="parentNodes" />
        <div 
          class="dndflowcompletion" 
          @drop="onDrop"
        >
          <FeedbackModal />
          <VueFlow 
            :default-viewport="{ zoom: 1.0, x: 0, y: 0 }" 
            class="completions" 
            :class="{ dark }" 
            @dragover="onDragOver"
          >
            <Background 
              :pattern-color="dark ? '#FFFFFB' : '#aaa'" 
              gap="8" 
            />
            <template #node-custom="{ data }">
              <ConditionNode 
                :data="data" 
                :type="'completion'"
              />
            </template>
            <template #node-dropzone="{ data }">
              <DropzoneNode :data="data" />
            </template>
            <template #node-feedback="{ data }">
              <FeedbackNode :data="data" />
            </template>
            <template #edge-condition="props">
              <CompletionLine v-bind="props" />
            </template>
            <MiniMap node-color="grey" />
          </VueFlow>
          <Sidebar 
            :conditions="completions" 
            :strings="store.state.strings" 
            :nodes="nodes"
            :edges="edges"
            @nodesIntersected="handleNodesIntersected" 
          />
        </div>

        <ChildNodes :child-nodes="childNodes" />
        <div class="d-flex justify-content-center">
          <Controls 
            :condition="'completion'"
            @change-class="toggleClass" 
          />
        </div>
      </div>
      <div v-else>
        Loading completion...
      </div>
    </div>
  </div>
</template>
<script setup>
// Import needed libraries
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import {  VueFlow, useVueFlow } from '@vue-flow/core'
import Sidebar from './CompletionSidebar.vue'
import { Background } from '@vue-flow/background'
import Controls from './CompletionControls.vue'
import ConditionNode from '../nodes/ConditionNode.vue'
import DropzoneNode from '../nodes/DropzoneNode.vue'
import { notify } from "@kyvg/vue3-notification"
import CompletionLine from '../edges/ConditionLine.vue'
import { MiniMap } from '@vue-flow/minimap'
import getNodeId from '../../composables/getNodeId'
import FeedbackNode from '../nodes/feedbackNode.vue'
import FeedbackModal from '../modals/FeedbackModal.vue'
import ChildNodes from '../charthelper/childNodes.vue'
import ParentNodes from '../charthelper/parentNodes.vue'

const { nodes, edges, addNodes, project, vueFlowRef, onConnect, addEdges, findNode } = useVueFlow({
  nodes: [],})

// Load Store 
const store = useStore();

// Define constants that will be referenced
const dark = ref(false)
// Toggle the dark mode fi child component emits event
function toggleClass() {
    dark.value = !dark.value;
}

// Function to go back
const goBack = () => {
  store.state.editingadding = !store.state.editingadding
  store.state.editingrestriction = !store.state.editingrestriction
}

// Get all available completions
const completions = ref(null);

// Intersected node
const intersectedNode = ref(null);

// Intersected node
const parentNodes = ref([]);
const childNodes = ref([]);

onMounted(async () => {
    try {
        completions.value = await store.dispatch('fetchCompletions');
    } catch (error) {
        console.error('Error fetching completions:', error);
    }
    const learningGoal = store.state.learninggoal[0];
    if (learningGoal && learningGoal.json && learningGoal.json.tree && learningGoal.json.tree.nodes) {
        learningGoal.json.tree.nodes.forEach((node) => {
            if (node.childCourse && node.childCourse.includes(store.state.node.node_id)) {
                parentNodes.value.push(node);
            } else if (node.parentCourse && node.parentCourse.includes(store.state.node.node_id)) {
                childNodes.value.push(node);
            }
        });
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
    data.visibility = true
    let parentCondition = 'starting_condition'

    let position = project({
      x: event.clientX - left,
      y: event.clientY - top,
    })

    const id = getNodeId('condition_', nodes.value)
    data.node_id = id

    if(intersectedNode.value){
      position.x = intersectedNode.value.dropzone.position.x
      position.y = intersectedNode.value.dropzone.position.y
      if(intersectedNode.value.dropzone.id == 'source_and'){
        parentCondition = intersectedNode.value.closestnode.id
        let parentConditionNode = findNode(parentCondition)
        if(parentConditionNode){
          parentConditionNode.childCondition = id
        }
      }else{
        parentCondition = 'starting_condition'
      }
    }

    const newNode = {
      id: id,
      type,
      position: { x: position.x , y: position.y },
      label: `${type} node`,
      data: data,
      draggable: false,
      parentCondition: parentCondition,
      childCondition: '',
    };

    addNodes([newNode])
    if(nodes.value.length == 1){
      addFeedbackNode(newNode)
    }
    if(intersectedNode.value){
      // Create an edge connecting the new drop zone node to the closest node
      let edgeData = {
        type: 'disjunctional',
        text: 'OR',
      }
      let targetHandle = 'target_or'
      if(intersectedNode.value.dropzone.id == 'source_and'){
        targetHandle = 'target_and'
        edgeData = {
          type: 'additional',
          text: 'AND',
        }
      }else{
        addFeedbackNode(newNode)
      }
      const newEdge = {
        id: intersectedNode.value.closestnode.id  + '-' + newNode.id,
        source: intersectedNode.value.closestnode.id,
        sourceHandle: intersectedNode.value.dropzone.id,
        target: newNode.id,
        targetHandle: targetHandle,
        type: 'condition',
        data: edgeData,
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

function addFeedbackNode (node) {
  const newFeedback = {
    id: node.id + '_feedback',
    type: 'feedback',
    position: { x: node.position.x , y: node.position.y-250 },
    label: `Feedback node`,
    data: {
      feedback: '',
      childCondition: node.id,
    },
    draggable: false,
  };
  const newEdge = {
    id: node.id  + '-' + newFeedback.id,
    source: node.id,
    sourceHandle: 'target_and',
    target: newFeedback.id,
    targetHandle: 'source_feedback',
  };
  addNodes([newFeedback]);
  addEdges([newEdge]);
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

.dndflowcompletion{flex-direction:column;display:flex;height:600px}.dndflowcompletion aside{color:#fff;font-weight:700;border-right:1px solid #eee;padding:15px 10px;font-size:12px;background:rgba(16,185,129,.75);-webkit-box-shadow:0px 5px 10px 0px rgba(0,0,0,.3);box-shadow:0 5px 10px #0000004d}.dndflowcompletion aside .nodes>*{margin-bottom:10px;cursor:grab;font-weight:500;-webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);box-shadow:5px 5px 10px 2px #00000040}.dndflowcompletion aside .description{margin-bottom:10px}.dndflowcompletion .vue-flow-wrapper{flex-grow:1;height:100%}@media screen and (min-width: 640px){.dndflowcompletion{flex-direction:row}.dndflowcompletion aside{min-width:25%}}@media screen and (max-width: 639px){.dndflowcompletion aside .nodes{display:flex;flex-direction:row;gap:5px}}
.learning-path-flow{background:#4e574f;}
.vue-flow__node.intersecting{background-color:#ff0}
.completions.dark{background:#4e574f;}

</style>