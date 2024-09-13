<template>
  <div>
    <button
      class="btn btn-outline-primary"
      :disabled="showBackConfirmation"
      @click="goBack"
    >
      <i class="fa fa-arrow-left" /> {{ store.state.strings.restriction_go_back_learningpath }}
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
      {{ store.state.strings.restriction_edit_restrictions }}
    </h3>
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">
          <i class="fa fa-check-circle" />
          {{ store.state.strings.restriction_restrictions_for }}
        </h5>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">
            <i
              :class="store.state.version ? 'fa fa-header' : 'fa fa-font'"
            />
            {{ store.state.strings.restriction_course_title }} {{ store.state.node.fullname }}
          </li>
          <li class="list-group-item">
            <i class="fa fa-tag" /> {{ store.state.strings.restriction_tags }} {{ store.state.node.tags }}
          </li>
        </ul>
        <div v-if="restrictions !== null">
          <ParentNodes :parent-nodes="parentNodes" />
          <div
            class="dndflowcompletion"
            @drop="onDrop"
          >
            <VueFlow
              class="completions"
              :default-viewport="{ zoom: 1.0, x: 0, y: 0 }"
              :class="{ dark }"
              :fit-view-on-init="true"
              :max-zoom="1.5"
              :min-zoom="0.2"
              :zoom-on-scroll="zoomLock"
              @dragover="onDragOver"
            >
              <Background
                :pattern-color="dark ? '#FFFFFB' : '#aaa'"
                gap="8"
              />
              <template #node-custom="{ data }">
                <ConditionNode
                  :data="data"
                  :type="'Restriction'"
                  :learningpath="learningpathrestriction"
                  @update-visibility="handleVisibility"
                />
              </template>
              <template #node-dropzone="{ data }">
                <DropzoneNode
                  :data="data"
                  :editorview="true"
                />
              </template>
              <template #edge-condition="props">
                <ConditionLine v-bind="props" />
              </template>
              <template #node-feedback="{ data }">
                <FeedbackNode
                  :data="data"
                  :learningpath="learningpathrestriction"
                  :visibility="visibility_emitted"
                  @update-feedback="handleFeedback"
                />
              </template>
            </VueFlow>
            <Sidebar
              :conditions="restrictions"
              :strings="store.state.strings"
              :nodes="nodes"
              :edges="edges"
              :type="'restriction'"
              :style="{ backgroundColor: backgroundSidebar }"
              @nodesIntersected="handleNodesIntersected"
            />
          </div>
          <ChildNodes :child-nodes="childNodes" />
          <div class="d-flex justify-content-center">
            <Controls
              :condition="'restriction'"
              :learningpath="learningpathrestriction"
              @change-class="toggleClass"
            />
          </div>
        </div>
        <div v-else>
          {{ store.state.strings.restriction_loading_restrictions }}
        </div>
      </div>
    </div>
  </div>
</template>
<script setup>
// Import needed libraries
import { ref, onMounted, nextTick, watch, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import ChildNodes from '../charthelper/childNodes.vue'
import ParentNodes from '../charthelper/parentNodes.vue'
import {  VueFlow, useVueFlow } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import DropzoneNode from '../nodes/DropzoneNode.vue'
import ConditionLine from '../edges/ConditionLine.vue'
import Sidebar from '../completion/CompletionSidebar.vue'
import Controls from '../completion/CompletionControls.vue'
import ConditionNode from '../nodes/ConditionNode.vue'
import getNodeId from '../../composables/getNodeId'
import { notify } from '@kyvg/vue3-notification'
import setZoomLevel from '../../composables/flowHelper/setZoomLevel'
import FeedbackNode from '../nodes/feedbackNodeRestriction.vue'

const { nodes, edges, addNodes, project, vueFlowRef,
  addEdges, findNode, toObject, fitView, viewport, zoomTo
} = useVueFlow({
  nodes: [],})

// Load Store
const store = useStore();
const learningpathrestriction= ref({})
const showBackConfirmation = ref(false)

const zoomLock = ref(false)

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

// Get all available restrictions
const restrictions = ref(null);
const visibility_emitted = ref(false)

// Intersected node
const intersectedNode = ref(null);

// Intersected node
const parentNodes = ref([]);
const childNodes = ref([]);

const backgroundSidebar = store.state.strings.LIGHT_STEEL_BLUE

onMounted(async () => {
  document.addEventListener('dragover', updateMousePosition);
  learningpathrestriction.value = props.learningpath
  try {
    restrictions.value = await store.dispatch('fetchRestrictions');
  } catch (error) {
      console.error('Error fetching conditions:', error);
  }
  if (learningpathrestriction.value.json && learningpathrestriction.value.json.tree &&
    learningpathrestriction.value.json.tree.nodes &&
    store.state.node) {
      learningpathrestriction.value.json.tree.nodes.forEach((node) => {
          if (node.childCourse && node.childCourse.includes(store.state.node.node_id)) {
              parentNodes.value.push(node);
          } else if (node.parentCourse && node.parentCourse.includes(store.state.node.node_id)) {
              childNodes.value.push(node);
          }
      });
  }
  setTimeout(() => {
    nextTick().then(() => {
      fitView({ duration: 1000 }).then(() => {
        zoomLock.value = true
        watch(
          () => viewport.value.zoom,
          (newVal, oldVal) => {
            if (newVal && oldVal && zoomLock.value) {
              if (newVal > oldVal) {
                setZoomLevel('in', zoomLock, viewport, zoomTo)
              } else if (newVal < oldVal) {
                setZoomLevel('out', zoomLock, viewport, zoomTo)
              }
            }
          },
          { deep: true }
        );
      });
    })
  }, 300)
});

// Function to go back
const goBack = () => {
  const condition = toObject()
  learningpathrestriction.value.json.tree.nodes.forEach(element_node => {
    if (
      store.state.node &&
      element_node.id === store.state.node.node_id
    ) {
        if (
          element_node.restriction == undefined &&
          condition.nodes.length == 0
        ) {
          goBackConfirmation(false)
        }
        if (
          element_node.restriction &&
          JSON.stringify(condition.nodes) == JSON.stringify(element_node.restriction.nodes)
        ) {
          goBackConfirmation(false)
        } else {
          showBackConfirmation.value = !showBackConfirmation.value
        }
      }
  });
}

const goBackConfirmation = (toggle) => {
  if (toggle) {
    goBack()
  }
  store.state.editingadding = !store.state.editingadding
  store.state.editingrestriction = !store.state.editingrestriction
}

// Prevent default event if node has been dropped
function onDragOver(event) {
  event.preventDefault()
  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}

// Prevent default event if node has been dropped
function handleNodesIntersected({ intersecting }) {
  intersectedNode.value = intersecting
}

const handleVisibility = (visibility) => {
  let visibilityNode = findNode(visibility.node_id)
  visibility_emitted.value = !visibility_emitted.value
  if (visibilityNode) {
    visibilityNode.data.visibility = visibility.visibility
  }
}

const handleFeedback = (feedback) => {
  let feedbackNode = findNode(feedback.childCondition + '_feedback')
  feedbackNode.data = feedback
}

const mousePosition = ref({ x: 0, y: 0 });

function updateMousePosition(event) {
  mousePosition.value = { x: event.clientX, y: event.clientY };
}

onBeforeUnmount(() => {
  document.removeEventListener('dragover', updateMousePosition);
});

// Adding setting up nodes and potentional edges
function onDrop(event) {
  visibility_emitted.value = !visibility_emitted.value
  if(nodes.value.length == 0 || intersectedNode.value){
    const type = event.dataTransfer?.getData('application/vueflow')
    const data = JSON.parse(event.dataTransfer?.getData('application/data'));
    const { left, top } = vueFlowRef.value.getBoundingClientRect()
    data.visibility = true
    let parentCondition = 'starting_condition'

    let event_clientX = event.clientX
    let event_clientY = event.clientY
    if (
      event_clientX == 0 &&
      event_clientY == 0
    ){
      event_clientX = mousePosition.value.x
      event_clientY = mousePosition.value.y
    }

    let position = project({
      x: event_clientX - left,
      y: event_clientY - top,
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
      deletable: false,
      parentCondition: parentCondition,
      childCondition: '',
    };

    addNodes([newNode]);
    if(nodes.value.length == 1){
      addFeedbackNode(newNode)
    }
    if(intersectedNode.value){
      // Create an edge connecting the new drop zone node to the closest node
      let edgeData = {
        type: 'disjunctional',
        text: store.state.strings.completion_edge_or,
      }
      let targetHandle = 'target_or'
      if(intersectedNode.value.dropzone.id == 'source_and'){
        targetHandle = 'target_and'
        edgeData = {
          type: 'additional',
          text: store.state.strings.completion_edge_and,
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
        draggable: false,
        deletable: false,
      };
      // Add the new edge
      addEdges([newEdge]);
    }
    } else{
    notify({
      title: store.state.strings.restriction_node_drop_refused_title,
      text: store.state.strings.restriction_node_drop_refused_text,
      type: 'warn'
    });
  }
}

function addFeedbackNode (node) {
  const newFeedback = {
    id: node.id + '_feedback',
    type: 'feedback',
    position: { x: node.position.x , y: node.position.y-345 },
    label: store.state.strings.completion_feedback_node,
    data: {
      visibility: true,
      feedback_before: "",
      feedback_before_checkmark: true,
      childCondition: node.id,
    },
    draggable: false,
    deletable: false,
  };
  const newEdge = {
    id: node.id  + '-' + newFeedback.id,
    source: node.id,
    sourceHandle: 'target_and',
    target: newFeedback.id,
    targetHandle: 'source_feedback',
    draggable: false,
    deletable: false,
  };
  addNodes([newFeedback]);
  addEdges([newEdge]);
}

</script>

<style scoped>
.dndflowcompletion{
  flex-direction:column;
  display:flex;
  height:600px}
.dndflowcompletion
aside{
  color:#fff;
  font-weight:700;
  border-right:1px solid #eee;
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
  cursor:grab;
  font-weight:500;
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
}@media screen and (min-width: 640px)
{
  .dndflowcompletion{flex-direction:row}
  .dndflowcompletion aside{min-width:20%}}
  @media screen and (max-width: 639px)
  {.dndflowcompletion aside
    .nodes{
      display:flex;
      flex-direction:row;
      gap:5px
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
  background-color: #f3eeee;
  border-radius: 0.5rem;
  padding: 0.25rem;
  margin: 0.25rem;
  box-shadow:0 5px 10px #0000004d;
  width: max-content;
}

</style>