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
    <div>
      <notifications width="100%" />
        <div v-if="$store.state.editingadding == false">
            <h3>{{store.state.strings.pluginname}}</h3>
            <div >
                <router-link :to="{ name: 'learninggoal-new' }" tag="button" class="btn btn-primary">{{store.state.strings.learninggoal_form_title_add}}</router-link>
            </div>
            <h2>{{store.state.strings.overviewlearningpaths}}</h2>

            <div >{{store.state.strings.learninggoals_edit_site_description}}</div>
                <span v-if="store.state.learningpaths == ''">
                    {{store.state.strings.learninggoals_edit_site_no_learningpaths}}
                </span>
                <span v-else>
                  <div v-for="singlelearninggoal in store.state.learningpaths" style="margin-bottom: 10px">
                      <div v-if="singlelearninggoal.name !== 'not found'">
                          <div>
                            <div class="card" style="width: 18rem;">
                              <div class="card-body">
                                <h5 class="card-title">{{ singlelearninggoal.name }}</h5>
                                <p class="card-text">{{ singlelearninggoal.description }}</p>
                                <router-link :to="{ name: 'learninggoal-edit', params: { learninggoalId: singlelearninggoal.id }}" :title="store.state.strings.edit">
                                  <i class="icon fa fa-pencil fa-fw iconsmall m-r-0" :title="store.state.strings.edit"></i>
                                </router-link>
                                <a href="" v-on:click.prevent="duplicateLearningpath(singlelearninggoal.id)" :title="store.state.strings.duplicate">
                                    <i class="icon fa fa-copy fa-fw iconsmall m-r-0" :title="store.state.strings.duplicate"></i>
                                </a>
                                <a href="" v-on:click.prevent="showDeleteConfirm(singlelearninggoal.id)" :title="store.state.strings.delete">
                                    <i class="icon fa fa-trash fa-fw iconsmall" :title="store.state.strings.delete"></i>
                                </a>
                                </div>
                            </div>
                          </div>
                          <div class="alert-danger p-3 m-t-1 m-b-1" v-show="clicked[singlelearninggoal.id]">
                              <div>{{store.state.strings.deletepromptpre}}{{singlelearninggoal.name}}{{store.state.strings.deletepromptpost}}</div>
                              <div class="m-t-1">
                                  <button class="btn btn-danger m-r-0" @click="deleteLearningpathConfirm(singlelearninggoal.id)" :title="store.state.strings.btnconfirmdelete">
                                  {{ store.state.strings.btnconfirmdelete }}</button>
                                  <button type=button @click="cancelDeleteConfirm(singlelearninggoal.id)" class="btn btn-secondary">{{store.state.strings.cancel}}</button>
                              </div>
                          </div>
                      </div>
                    </div>
                </span>
        </div>
        <div v-if="$store.state.editingadding == true">
      <h3>{{ store.state.strings.learninggoal_form_title_edit }}</h3>
      <div>
        <div v-for="goal in store.state.learninggoal">
          <p>
            <h4>{{ store.state.strings.fromlearningtitel }}</h4>
            <input
              v-if="$store.state.learningGoalID == 0"
              :placeholder="store.state.strings.goalnameplaceholder"
              autofocus
              type="text"
              v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
              v-model="goalname"
            />
            <input
              v-else
              type="text"
              v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
              v-model="goal.name"
            />
          </p>
          <p>
            <h4>{{ store.state.strings.fromlearningdescription }}</h4>
            <input
              v-if="$store.state.learningGoalID == 0"
              :placeholder="store.state.strings.goalsubjectplaceholder"
              type="textarea"
              v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
              v-model="goaldescription"
            />
            <input
              v-else
              type="textarea"
              v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
              v-model="goal.description"
            />
          </p>
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
                @node-count-changed="updateNumberOfNodesInChild"
                @change-class="toggleClass"
              />
            </div>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Import needed libraries
import { ref, onMounted, watch, nextTick } from 'vue'
import { onBeforeRouteUpdate } from 'vue-router';
import { MarkerType, VueFlow, useVueFlow } from '@vue-flow/core'
import { useStore } from 'vuex'
import Sidebar from './flowchart/Sidebar.vue'
import Controls from './flowchart/Controls.vue'
import CustomrNode from './flowchart/CustomNode.vue'
import { useRouter } from 'vue-router'
import { notify } from "@kyvg/vue3-notification"
import { Background } from '@vue-flow/background'
import Modal from './modals/Modal.vue'

// Load Store and Router
const store = useStore()
const router = useRouter()

// Define constants that will be referenced
const goalname = ref('')
const goaldescription = ref('')
const clicked = ref({})
const dark = ref(false)

// Toggle the dark mode fi child component emits event
function toggleClass() {
  dark.value = !dark.value;
}

// Update the variable when the custom event is emitted
let id = ref(0);
const updateNumberOfNodesInChild = (count) => {
  id.value = count;
};

// generate a new id
function getId() {
  id.value = id.value+1;
  return `dndnode_${id.value}`
}

// load useVueFlow properties / functions
const { nodes, findNode, onConnect, addEdges, addNodes, project, vueFlowRef, removeEdges } = useVueFlow({
  nodes: [],
})
// Watch for changes in the number of nodes
const numberOfNodes = ref(nodes.length);
watch(nodes, () => {
  numberOfNodes.value = nodes.length;
});

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
    showPreviewConnection(event.node.id, clostestNode.id)
  }else{
    removeEdges('preview_edge')
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
function showPreviewConnection(targetId, sourceId ) {
  const previewEdge = {
    id: 'preview_edge',
    source: targetId,
    target: sourceId,
    sourceHandle: 'source',
    targetHandle: 'target',
    animated: true,
    style: {
      stroke: 'green',
      'stroke-width': 3,
    },
  };
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
  console.log(params);
  params.markerEnd = MarkerType.ArrowClosed;
  if (params.source !== store.state.startnode) {
    // Swap source and target positions
    params.target = params.source;
    params.source = store.state.startnode;
  }
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

// Checking routes 
const checkRoute = (currentRoute) => {
    if(currentRoute == undefined){
        router.push({ name: 'learninggoals-edit-overview' });
    }
  else if (currentRoute.name === 'learninggoal-edit') {

    store.state.editingadding = true;
    nextTick(() => showForm(currentRoute.params.learninggoalId));
  } else if (currentRoute.name === 'learninggoal-new') {
    store.state.editingadding = true;
    nextTick(() => showForm(null));
  }
};

// Trigger web services on mount
onMounted(() => {
  store.dispatch('fetchLearningpaths');
  store.dispatch('fetchAvailablecourses');
  checkRoute(router.value);
});

// Delete confirmation before learning path will be deleted
const showDeleteConfirm = (index) => {
  clicked.value = {};
  clicked.value[index] = true;
};

// Cancel learning path deletion
const cancelDeleteConfirm = (index) => {
  if (clicked.value.hasOwnProperty(index)) clicked.value[index] = !clicked.value[index];
};

// Deleting learning path
const deleteLearningpathConfirm = (learninggoalid) => {
  const result = {
    learninggoalid: learninggoalid,
  };
  store.dispatch('deleteLearningpath', result);
  clicked.value = {};
  notify({
    title: store.state.strings.title_delete,
    text: store.state.strings.description_delete,
    type: 'warn'
  });
};

// Duplicate learning path
const duplicateLearningpath = (learninggoalid) => {
  const result = {
    learninggoalid: learninggoalid,
  };
  store.dispatch('duplicateLearningpath', result);
  notify({
    title: store.state.strings.title_duplicate,
    text: store.state.strings.description_duplicate,
    type: 'success'
  });
};

// Showing form to generate or edit learning path
const showForm = async (learninggoalId = null) => {
  goalname.value = ''
  goaldescription.value = ''
  if (learninggoalId) {
    store.state.learningGoalID = learninggoalId;
    store.dispatch('fetchLearningpath')
    store.state.editingadding = true
    // Do something here in case of an edit.
  } else {
    store.dispatch('fetchLearningpath')
    store.state.editingadding = true
    // Do something here in case of an add.
  }
  window.scrollTo(0, 0)
  // This has to happen after the save button is hit.
};

// Watch changes on goalname
watch(goalname, (newGoalName) => {
  store.state.learninggoal[0].name = newGoalName;
});

// Watch changes on goaldescription
watch(goaldescription, (newGoalDescription) => {
  store.state.learninggoal[0].description = newGoalDescription;
});

// Trigger the checking route function
onBeforeRouteUpdate((to, from, next) => {
  checkRoute(to);
  next();
});
</script>