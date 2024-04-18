<template>
  <div>
    <button 
      class="btn btn-outline-primary"
      :disabled="showBackConfirmation"
      @click="goBack" 
    >
      <i class="fa fa-arrow-left" /> {{ store.state.strings.completion_go_back_learningpath }}
    </button>
    <div 
      v-if="showBackConfirmation"
      class="cancelConfi"
    >
      {{ store.state.strings.flowchart_cancel_confirmation }}
      <button 
        id="cancel-learning-path"
        class="btn btn-primary m-2" 
        @click="goBack"
      >
        {{ store.state.strings.flowchart_back_button }}
      </button>
      <button 
        id="confim-cancel-learning-path"
        class="btn btn-warning m-2"
        @click="goBackConfirmation(true)"
      >
        {{ store.state.strings.flowchart_cancel_button }}
      </button>
    </div>
    <h3>
      {{ store.state.strings.completion_edit_completion }}
    </h3>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">
          <i class="fa fa-check-circle" /> 
          {{ store.state.strings.completion_completion_for }}
        </h5>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">
            <i class="fa fa-header" /> {{ store.state.strings.completion_course_title }} {{ store.state.node.fullname }}
          </li>
          <li class="list-group-item">
            <i class="fa fa-tag" /> {{ store.state.strings.completion_course_tags }} {{ store.state.node.tags }}
          </li>
        </ul>
        <div v-if="completions !== null">
          <ParentNodes :parent-nodes="parentNodes" />
          <div 
            class="dndflowcompletion" 
            @drop="onDrop"
          >
            <FeedbackModal :learningpath="learningpathcompletion" />
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
                  :learningpath="props.learningpath"
                  @update-visibility="handleVisibility"
                />
              </template>
              <template #node-dropzone="{ data }">
                <DropzoneNode :data="data" />
              </template>
              <template #node-feedback="{ data }">
                <FeedbackNode 
                  :data="data"
                  :learningpath="learningpathcompletion"
                  :visibility="visibility_emitted"
                  @update-feedback="handleFeedback"
                />
              </template>
              <template #edge-condition="props">
                <CompletionLine v-bind="props" />
              </template>
            </VueFlow>
            <Sidebar 
              :conditions="completions" 
              :strings="store.state.strings" 
              :nodes="nodes"
              :edges="edges"
              :type="'Completion'"
              :style="{ backgroundColor: backgroundSidebar }"
              @nodesIntersected="handleNodesIntersected" 
            />
          </div>
          <ChildNodes :child-nodes="childNodes" />
          <div class="d-flex justify-content-center">
            <Controls 
              :condition="'completion'"
              :learningpath="learningpathcompletion"
              @change-class="toggleClass" 
            />
          </div>
        </div>
        <div v-else>
          {{ completion_loading_completion }}
        </div>
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
import getNodeId from '../../composables/getNodeId'
import FeedbackNode from '../nodes/feedbackNode.vue'
import FeedbackModal from '../modals/FeedbackModal.vue'
import ChildNodes from '../charthelper/childNodes.vue'
import ParentNodes from '../charthelper/parentNodes.vue'

const { nodes, edges, addNodes, project, vueFlowRef, onConnect,
  addEdges, findNode, toObject } = useVueFlow({
  nodes: [],})

// Load Store 
const store = useStore();
const learningpathcompletion= ref({})
const showBackConfirmation = ref(false)
const visibility_emitted = ref(false)

const props = defineProps({
  learningpath: {
    type: Object,
    default: null,
  }
});

// Define constants that will be referenced
const dark = ref(false)
// Toggle the dark mode fi child component emits event
function toggleClass() {
    dark.value = !dark.value;
}

// Function to go back
const goBack = () => {
  const condition = toObject()
  learningpathcompletion.value.json.tree.nodes.forEach(element_node => {
    if (
      store.state.node &&
      element_node.id === store.state.node.node_id
    ) {
        if (
          element_node.completion == undefined &&
          condition.nodes.length == 0
        ) {
          goBackConfirmation(false)
        }
        if (
          element_node.completion &&
          JSON.stringify(condition.nodes) == JSON.stringify(element_node.completion.nodes)
        ) {
          goBackConfirmation(false)
        } else {
          showBackConfirmation.value = !showBackConfirmation.value
        }
      }
  });
}

const handleFeedback = (feedback) => {
  let feedbackNode = findNode(feedback.childCondition + '_feedback')
  feedbackNode.data = feedback
}

const handleVisibility = (visibility) => {
  let visibilityNode = findNode(visibility.node_id)
  visibility_emitted.value = !visibility_emitted.value
  visibilityNode.data.visibility = visibility.visibility
}

const goBackConfirmation = (toggle) => {
  if (toggle) {
    goBack()
  }
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

const backgroundSidebar = store.state.strings.DARK_ORANGE

onMounted(async () => {
    learningpathcompletion.value = props.learningpath
    try {
        completions.value = await store.dispatch('fetchCompletions');
    } catch (error) {
        console.error('Error fetching completions:', error);
    }
    if (learningpathcompletion.value.json && learningpathcompletion.value.json.tree &&
      learningpathcompletion.value.json.tree.nodes) {
        learningpathcompletion.value.json.tree.nodes.forEach((node) => {
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
      title: store.state.strings.completion_node_refused_title,
      text: store.state.strings.completion_node_refused_text,
      type: 'warn'
    });
  }
}

function addFeedbackNode (node) {
  const newFeedback = {
    id: node.id + '_feedback',
    type: 'feedback',
    position: { x: node.position.x , y: node.position.y-495 },
    label: store.state.strings.completion_feedback_node,
    data: {
      feedback_before: "",
      feedback_after: "",
      feedback_before_checkmark: true,
      feedback_after_checkmark: true,
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

.dndflowcompletion{
  flex-direction:column;
  display:flex;
  height:600px
}
.dndflowcompletion aside
{
  color:#fff;
  font-weight:700
  ;border-right:1px solid #eee;
  padding:15px 10px;
  font-size:12px;
  -webkit-box-shadow:0px 5px 10px 0px rgba(0,0,0,.3);
  box-shadow:0 5px 10px #0000004d;
  border-top-right-radius: 1rem;
  border-bottom-right-radius: 1em;
}
.dndflowcompletion aside 
.nodes>*
{
  margin-bottom:10px;
  cursor:grab;font-weight:500;
  -webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);
  box-shadow:5px 5px 10px 2px #00000040
}
.dndflowcompletion aside 
.description{
  margin-bottom:10px
}
.dndflowcompletion 
.vue-flow-wrapper
{
  flex-grow:1;
  height:100%
}@media screen and (min-width: 640px){
  .dndflowcompletion{flex-direction:row}
  .dndflowcompletion aside{min-width:20%}
}
@media screen and (max-width: 639px){
  .dndflowcompletion aside 
  .nodes{
    display:flex;
    flex-direction:row;gap:5px
  }
}
.completions{
  border-top-left-radius: 1rem;
  border-bottom-left-radius: 1em;
}
.completions.dark{background:#4e574f;}

.cancelConfi{
  z-index: 1;
  position: absolute;
  background-color: lightgray;
  border-radius: 0.5rem;
  padding: 0.25rem;
  margin: 0.25rem;
}

</style>