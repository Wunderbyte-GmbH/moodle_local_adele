
<template>
  <div>
    <div
      class="dndflow mt-4"
      @drop="onDrop"
      @wheel="onWheel($event, zoomLockVaraible, viewport, zoomTo)"
    >
      <Modal
        v-if="store.state.view != 'teacher'"
        :learningpath="learningpath"
        @saveEdit="handleSaveEdit"
      />
      <CourseModal
        v-if="store.state.view != 'teacher'"
        :learningpath="learningpath"
        @saveEditCourse="handleSaveEditCourse"
      />
      <VueFlow
        :default-viewport="{ zoom: 1.0, x: 0, y: 0 }"
        :class="{ dark }"
        :fit-view-on-init="true"
        :max-zoom="1.55"
        :min-zoom="0.15"
        :zoom-on-scroll="false"
        :zoom-on-pinch="false"
        class="learning-path-flow"
        @dragover="onDragOver"
        @node-drag="onDrag"
        @node-click="onNodeClickCall"
        :delete-key-code="['Backspace', 'Delete']"
      >
        <Background
          :pattern-color="dark ? '#FFFFFB' : '#aaa'"
          gap="8"
        />
        <template #node-custom="{ data }">
          <CustomNode
            :data="data"
            :learningpath="learningpath"
            :editorview="editor_view"
            @change-module="onChangeModule"
            @delete-node="onRemoveNode"
          />
        </template>
        <template #node-dropzone="{ data }">
          <DropzoneNode
            :data="data"
            :editorview="editor_view"
          />
        </template>
        <template #node-conditionaldropzone="{ data }">
          <ConditionalDropzoneNode :data="data" />
        </template>
        <template #node-orcourses="{ data }">
          <OrCourses
            :data="data"
            :learningpath="learningpath"
            :editorview="editor_view"
            @typeChange="typeChanged"
            @change-module="onChangeModule"
            @delete-node="onRemoveNode"
          />
        </template>
        <template #node-module="{ data }">
          <ModuleNode
          :data="data"
          :zoomstep="zoomstep"
          />
        </template>
        <template #node-expandedcourses="{ data }">
          <ExpandNodeEdit
            :data="data"
            :zoomstep="zoomstep"
          />
        </template>
        <MiniMap
          v-if="shouldShowMiniMap"
          node-color="grey"
        />
      </VueFlow>
      <Sidebar
        v-if="store.state.view != 'teacher' &&editor_view"
        :courses="store.state.availablecourses"
        :learningmodule="learningpath"
        :strings="store.state.strings"
        :style="{ backgroundColor: backgroundSidebar }"
        @nodesIntersected="handleNodesIntersected"
        @changedModule="onChangedModule"
      />
    </div>
    <p />
    <div
      class="d-flex justify-content-center"
    >
      <Controls
        :learningpath="learningpath"
        @change-class="toggleClass"
        @change-user-view="toggleUserView"
        @finish-edit="finishEdit"
      />
    </div>
    <p />
  </div>
</template>

<script setup>
// Import needed libraries
import { ref, watch, nextTick, onMounted, computed, onUnmounted } from 'vue'
import { VueFlow, useVueFlow } from '@vue-flow/core'
import { useStore } from 'vuex'
import Sidebar from './SidebarPath.vue'
import Controls from './ControlsPath.vue'
import CustomNode from '../nodes/CustomNode.vue'
import { Background } from '@vue-flow/background'
import Modal from '../modals/ModalNode.vue'
import CourseModal from '../modals/ModalCourse.vue'
import { MiniMap } from '@vue-flow/minimap'
import getNodeId from '../../composables/getNodeId'
import DropzoneNode from '../nodes/DropzoneNode.vue'
import ModuleNode from '../nodes/ModuleNode.vue'
import ConditionalDropzoneNode from '../nodes/ConditionalDropzoneNode.vue'
import OrCourses from '../nodes/OrCourses.vue'
import { notify } from "@kyvg/vue3-notification"
import shiftNodesDown from '../../composables/shiftNodesDown'
import setStartingNode from '../../composables/setStartingNode';
import addCustomEdge from '../../composables/addCustomEdge';
import removeDropzones from '../../composables/removeDropzones';
import addAutoCompletions from '../../composables/conditions/addAutoCompletions'
import addStagCompletions from '../../composables/conditions/addStagCompletions'
import addAutoRestrictions from '../../composables/conditions/addAutoRestrictions'
import addAndConditions from '../../composables/conditions/addAndConditions'
import drawModules from '../../composables/nodesHelper/drawModules'
import ExpandNodeEdit from '../nodes/ExpandNodeEdit.vue'
import onNodeClick from '../../composables/flowHelper/onNodeClick'
import { debounce } from 'lodash';
import onWheel from '../../composables/flowHelper/onWheel'
import recalculateParentChild from '../../composables/recalculateParentChild'

// Load Store and Router
const store = useStore()

const props = defineProps({
  learningpath: {
    type: Object,
    required: true,
  }
});

// Emit to parent component
const emit = defineEmits([
  'finish-edit',
  'removeNodeConditions',
  'add-edge',
  'remove-edge',
  'save-edit',
  'move-node',
  'changedModule',
  'changedLearningpathTree',
  'save-edit-course',
  'removeNode'
]);
// Define constants that will be referenced
const dark = ref(false)
const editor_view = ref(true)
// Intersected node
const intersectedNode = ref(null);
// check the page width
const dndFlowWidth = ref(0);
const backgroundSidebar = store.state.strings.DEEP_SKY_BLUE

const shouldShowMiniMap = computed(() => {
  return dndFlowWidth.value > 768;
});

const zoomLockVaraible = ref(false)
const zoomstep = ref(0)
const undoWatcher = ref(null);

const finishEdit = () => {
  emit('finish-edit');
}

// load useVueFlow properties / functions
const { nodes, edges, findNode, onConnect, addEdges, zoomTo,
    addNodes, removeNodes, fitView, viewport, setCenter,
    toObject, getEdges, onNodeDragStop } = useVueFlow({
nodes: [],
edges: [],
})

onMounted(() => {
  store.state.undoNodes = []
  nextTick(() => {
    startUndoWatcher();
  });
  const observer = new ResizeObserver(entries => {
  for (let entry of entries) {
      if (entry.target.classList.contains('dndflow')) {
        dndFlowWidth.value = entry.contentRect.width;
        break;
      }
    }
  });
  const targetElement = document.querySelector('.dndflow');

  if (targetElement) {
    observer.observe(targetElement);
  }
  setTimeout(() => {
    nextTick().then(() => {
      fitView({ duration: 1000 })
    })
  }, 300)
});

// Toggle the dark mode fi child component emits event
function toggleClass() {
    dark.value = !dark.value;
}

function toggleUserView() {
  editor_view.value = !editor_view.value;
}

function onChangedModule(learningpath) {
  emit('changedModule', learningpath)
}

const typeChanged = (changedNode) => {
  nodes.value.forEach((node) => {
    if ( node.id == changedNode.id) {
      node.type = 'custom'
      node.completion.nodes.forEach((completion_node) => {
        if (completion_node.type == 'custom' && completion_node.data.label == 'course_completed' &&
          completion_node.data.value != undefined) {
            delete completion_node.data.value;
        }
      })
    }
  })
}

const onChangeModule = (data) => {
  let learningpath_temp = props.learningpath
  if (learningpath_temp.json.tree.nodes) {
    learningpath_temp.json.tree.nodes.forEach((node) => {
      if (node.id == data.node_id) {
        node.data = data
      }
    })
  }
}

onNodeDragStop(({node}) => {
  emit('move-node', node);
})

const onDrag = ($event) => {
  $event.nodes[0].position = {
    x: Math.round($event.nodes[0].position.x / 150) * 150,
    y: Math.round($event.nodes[0].position.y / 150) * 150,
  }
  if (typeof $event.nodes[0].data.module == 'number') {
    drawModules(props.learningpath, addNodes, removeNodes, findNode, $event.nodes[0])
  }
}

const onNodeClickCall = (event) => {
  zoomstep.value = onNodeClick(event, setCenter, store )
}

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

// Adjust and add edges if connection was made
async function handleConnect(params) {
if (params.source !== store.state.startnode) {
 // Swap source and target positions
 params.target = params.source;
 params.source = store.state.startnode;
}

addEdges(addCustomEdge( params.target, params.source));
emit('add-edge', addCustomEdge( params.target, params.source));

props.learningpath.json.tree =
recalculateParentChild(props.learningpath.json.tree, 'parentCourse', 'childCourse', 'starting_node')
// await store.dispatch('saveLearningpath', props.learningpath);
}

function handleSaveEdit(params){
  emit('save-edit', params);
}

function handleSaveEditCourse(params){
  emit('save-edit-course', params);
}

// Triggers handle connect
onConnect(handleConnect);

watch(
  () => edges.value.length,
  (newEdges, oldEdges) => {
    if(oldEdges > newEdges){
      emit('remove-edge', edges.value);
    }
  },
  { deep: true }
);

// Adding setting up nodes and potentional edges
function onDrop(event) {
  if(intersectedNode.value){
    const type = event.dataTransfer?.getData('application/vueflow')
    const data = JSON.parse(event.dataTransfer?.getData('application/data'));
    if (data.selected_course_image) {
      data.imagepaths = {
        [data.course_node_id]: data.selected_course_image, // Corrected this line
      }
    }
    const position = {
      x: intersectedNode.value.closestnode.computedPosition.x,
      y: intersectedNode.value.closestnode.computedPosition.y,
    }

    const id = getNodeId('dndnode_', nodes.value);
    let addConditions = {}
    data.node_id = id;

    //if is starting node dz
    let parentCourse = []
    let childCourse = []

    let newNode = {
      id: id,
      type,
      label: `${type} node`,
      draggable: true,
      deletable: false,
    }
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
          shiftNodesDown(data, nodes.value)
        }
    }else if(intersectedNode.value.dropzone.id == 'dropzone_child'){
      parentCourse.push(intersectedNode.value.closestnode.id)
      intersectedNode.value.closestnode.childCourse.push(data.node_id)
      const extraImageShift = intersectedNode.value.closestnode.data.selected_course_image ? 200 : 0
      position.y += + intersectedNode.value.dropzone.dimensions.height/2 + 300 + extraImageShift
    }else if(intersectedNode.value.dropzone.id == 'dropzone_and'){
      addConditions = addAndConditions(intersectedNode.value, getEdges, id)
      position.x += 200
      parentCourse = addConditions.parentNodes
      childCourse = addConditions.childNodes
      newNode.restriction = addConditions.newRestrictions
      newNode.completion = addConditions.newCompletions
      newNode.data = data
      nodes.value.forEach((node) => {
        if (addConditions.newOtherRestrictions.includes(node.id)){
          node = addAutoRestrictions(newNode, node, 'and', store)
        }
      })
    }
    else if(intersectedNode.value.dropzone.id == 'dropzone_or'){
      // get the clostestnode and change type and data
      let dropzoneNode = intersectedNode.value.closestnode
      if ( !dropzoneNode.data.course_node_id.includes(data.course_node_id[0])) {
        dropzoneNode.type = 'orcourses'
        dropzoneNode.data.course_node_id.push(data.course_node_id[0])
        nodes.value.forEach((node) => {
          if (node.id == dropzoneNode.id){
            if (data.imagepaths) {
              if (dropzoneNode.data.imagepaths) {
                dropzoneNode.data.imagepaths = {...dropzoneNode.data.imagepaths, ...data.imagepaths}
              }else {
                dropzoneNode.data.imagepaths = data.imagepaths
                dropzoneNode.data.selected_course_image = data.selected_course_image
              }
            }
            if (dropzoneNode.data.course_node_id.length == 2 &&
              dropzoneNode.data.fullname == dropzoneNode.data.shortname ) {
              dropzoneNode.data.fullname = store.state.strings.nodes_collection;
            }
            node = dropzoneNode
            node = addStagCompletions(node)
          }
        })
      } else {
        notify({
          title: store.state.strings.flowchart_course_already_inside_title,
          text: store.state.strings.flowchart_course_already_inside_text,
          type: 'warn'
        });
      }
    }
    if (intersectedNode.value.closestnode.position.x < intersectedNode.value.dropzone.position.x) {
      position.x += intersectedNode.value.closestnode.dimensions.width
    }

    newNode = { ...newNode, ...{
        data: data,
        parentCourse: parentCourse,
        childCourse: childCourse,
        position,
      }
    }

    if((intersectedNode.value.dropzone.id.includes('dropzone_') &&
      !intersectedNode.value.dropzone.id.includes('_and')) &&
      !intersectedNode.value.dropzone.id.includes('_or')){

      newNode = addAutoCompletions(newNode, store)
      let source = intersectedNode.value.closestnode.id
      let target = newNode.id
      if(intersectedNode.value.dropzone.id.includes('child')){
        source = newNode.id
        target = intersectedNode.value.closestnode.id
        newNode = addAutoRestrictions(newNode, intersectedNode.value.closestnode, 'child', store)
        newNode.position = {
          x: Math.round(newNode.position.x / 150) * 150,
          y: Math.round(newNode.position.y / 150) * 150,
        }
        addNodes([newNode])
      }else{
        addAutoRestrictions(newNode, intersectedNode.value.closestnode, 'parent', store)
        newNode.position = {
          x: Math.round(newNode.position.x / 150) * 150,
          y: Math.round(newNode.position.y / 150) * 150,
        }
        addNodes([newNode])
      }
      // Add the new edge
      addEdges(addCustomEdge(source, target));
    } else if (!intersectedNode.value.dropzone.id.includes('_or')) {
      // Add Completion addAutoAndCompletions.
      newNode = addAutoCompletions(newNode, store)
      addNodes([newNode])
      if (addConditions.newEdges) {
        addEdges(addConditions.newEdges)
      }
    }
    let tree = toObject()
    tree = removeDropzones(tree)
    emit('changedLearningpathTree', tree)
    if(intersectedNode.value.closestnode.id == 'starting_node'){
      setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 600, store)
    }
  } else{
    notify({
      title: store.state.strings.flowchart_drop_refused_title,
      text: store.state.strings.flowchart_drop_refused_text,
      type: 'warn'
    });
  }
}

watch(
  () => nodes.value.length,
  (newNodes, oldNodes) => {
    if(oldNodes > newNodes){
      if (props.learningpath.json && props.learningpath.json.tree) {
        const deletedNode = props.learningpath.json.tree.nodes.filter(item => !nodes.value.some(otherItem => otherItem.id === item.id))
        if (deletedNode[0]) {
          emit('removeNode', deletedNode[0]);
        }
        setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 600, store, true)
        if (deletedNode[0] && deletedNode[0].id) {
          drawModules(props.learningpath, addNodes, removeNodes, findNode, null, deletedNode[0].id)
        }
      }
    }
  },
);

const debouncedHandler = debounce((newVal, oldVal) => {
  if (newVal.length < oldVal.length) {
    const lastOldVal = oldVal[oldVal.length - 1];
    nodes.value = [...lastOldVal]
    const lastEdgesVal = store.state.undoEdges[store.state.undoEdges.length - 1];
    edges.value = [...lastEdgesVal]
    store.commit('unsetUndoEdges');
    setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 800, store)
  }
}, 300);

watch(
  () => store.state.undoNodes,
  debouncedHandler,
  { deep: true }
);
const startUndoWatcher = () => {
  if (!undoWatcher.value) {
    undoWatcher.value = watch(
      () => store.state.undoNodes,
        debouncedHandler,
    );
  }
};

onUnmounted(() => {
  if (undoWatcher.value) {
    undoWatcher.value();
    store.state.undoNodes = []
  }
});

async function onRemoveNode(data) {
    let node = findNode(data.node_id)
    let confirmation = true;
    if (node.type != 'module') {
      confirmation = window.confirm(store.state.strings.flowchart_delete_confirmation + node.data.fullname + '?');
    }
    if (confirmation) {
      const undoNodesSet = [...nodes.value]
      const undoEdgesSet = [...edges.value]
      await store.dispatch('setUndoNodes', {
        undoNodesSet: undoNodesSet,
        undoEdgesSet: undoEdgesSet,
      });
      node.deletable = true;
      removeNodes(data.node_id)
      emit('removeNodeConditions', data.node_id);
    }
}

</script>

<style>
.vue-flow__edge-layer {
  z-index: 0; /* Ensure edges are below */
}

.vue-flow__node-layer {
  z-index: 10; /* Ensure nodes are above */
}
</style>
<style scoped>
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/theme-default.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';

.dndflow{
  flex-direction:column;
  display:flex;height:600px
}
.dndflow aside{
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
.dndflow aside
.nodes>*{
  margin-bottom:10px;
  cursor:grab;
  font-weight:500;
  -webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);
  box-shadow:5px 5px 10px 2px #00000040
}
.dndflow aside
.description{
  margin-bottom:10px
}
.dndflow
.vue-flow-wrapper{
  flex-grow:1;
  height:100%
}
@media screen and (min-width: 640px)
{
  .dndflow{flex-direction:row}
  .dndflow
  aside{min-width:20%}
}
@media screen and (max-width: 639px)
{
  .dndflow aside
  .nodes{
    display:flex;
    flex-direction:row;
    gap:5px
  }
}
.learning-path-flow{
  border-top-left-radius: 1rem;
  border-bottom-left-radius: 1em;
}
.learning-path-flow.dark{
  background:#4e574f;
}
</style>