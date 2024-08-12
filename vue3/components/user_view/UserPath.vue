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

<template>
  <div>
    <notifications width="100%" />
    <div>
      <button
        v-if="store.state.view!='student'"
        class="btn btn-outline-primary"
        @click="goBack"
      >
        <i class="fa fa-arrow-left" /> {{ store.state.strings.user_view_go_back_overview }}
      </button>
      <h2
        v-if="store.state.view!='student'"
        class="mt-3"
      >
        {{ store.state.strings.user_view_user_path_for }}
      </h2>
      <div
        v-if="user_learningpath"
        class="card"
      >
        <div v-if="store.state.view!='student'">
          <div class="card-body">
            <h5 class="card-title">
              <i
                :class="store.state.version ? 'fa fa-user-circle' : 'fa fa-user'"
              />
              {{ user_learningpath.username }}
            </h5>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">
                <i class="fa fa-user" /> {{ store.state.strings.user_view_firstname }}: {{ user_learningpath.firstname }}
              </li>
              <li class="list-group-item">
                <i class="fa fa-user" /> {{ store.state.strings.user_view_lastname }}: {{ user_learningpath.lastname }}
              </li>
              <li class="list-group-item">
                <i class="fa fa-envelope" /> {{ store.state.strings.user_view_email }}: {{ user_learningpath.email }}
              </li>
            </ul>
          </div>
        </div>
        <div
          style="width: 100%; height: 600px;"
        >
          <VueFlow
            :nodes="nodes"
            :edges="edges"
            :viewport="viewport"
            :default-viewport="viewport"
            :max-zoom="1.5"
            :min-zoom="0.2"
            :zoom-on-scroll="zoomLock"
            class="learning-path-flow"
            @node-click="onNodeClickCall"
          >
            <template #node-custom="{ data }">
              <CustomNodeEdit
                :data="data"
                :learningpath="user_learningpath"
                :zoomstep="zoomstep"
              />
            </template>
            <template
              #node-orcourses="{ data }"
            >
              <CustomStagNodeEdit
                :data="data"
                :learningpath="user_learningpath"
                :zoomstep="zoomstep"
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
            <template #edge-custom="props">
              <TransitionEdge
                v-bind="props"
                :hidden="props.data.hidden"
                @end-transition="handleZoomLock"
              />
            </template>
          </VueFlow>
        </div>
        <div
          v-if="store.state.view != 'student'"
          class="d-flex justify-content-center control-btns"
        >
          <Controls />
        </div>
      </div>
    </div>
  </div>
</template>

  <script setup>
  // Import needed libraries
import { nextTick, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router'
import { useStore } from 'vuex';
import { VueFlow, useVueFlow } from '@vue-flow/core'
import TransitionEdge from '../edges/TransitionEdge.vue'
import CustomNodeEdit from '../nodes/CustomNodeEdit.vue'
import CustomStagNodeEdit from '../nodes/CustomStagNodeEdit.vue'
import ExpandNodeEdit from '../nodes/ExpandNodeEdit.vue'
import ModuleNode from '../nodes/ModuleNode.vue'
import Controls from '../user_view/UserControls.vue'
import drawModules from '../../composables/nodesHelper/drawModules'
import outerGraphDisplay from '../../composables/flowHelper/outerGraphDisplay'
import innerGraphDisplay from '../../composables/flowHelper/innerGraphDisplay'
import onNodeClick from '../../composables/flowHelper/onNodeClick';

// Load Router
const router = useRouter()
const route = useRoute()

// Load Store
const store = useStore()

const { addNodes, addEdges, removeNodes, removeEdges,
  findNode, zoomTo, viewport, setCenter } = useVueFlow()

// Function to go back
const goBack = () => {
  router.go(-1) // Go back one step in the history
}

const props = defineProps({
  userlearningpathparent: {
    type: Object,
    default: null,
  }
});

// Declare reactive variable for nodes
const nodes = ref([]);
const edges = ref([]);
const zoomSteps = [ 0.2, 0.25, 0.35, 0.55, 0.85, 1.15, 1.5]
const zoomLock = ref(false)
const zoomstep = ref(0)
const user_learningpath = ref({})

onMounted( async () => {
  if(!props.user_learningpath_parent){
    let params = []
    if (store.state.view == 'student') {
      params = {
        learningpathId: store.state.learningPathID,
        userId: store.state.user,
      }
    }else {
      params = route.params
    }
    user_learningpath.value  = await store.dispatch('fetchUserPathRelation', params)
  } else {
    user_learningpath.value = props.user_learningpath_parent
  }
  if(user_learningpath.value){
    setFlowchart()
    setTimeout(() => {
      nextTick().then(() => {
        const topNode = nodes.value.reduce((top, node) => (node.position.y < top.position.y ? node : top), nodes.value[0]);
        const minX = Math.min(...nodes.value.map(node => node.position.x));
        const maxX = Math.max(...nodes.value.map(node => node.position.x));
        const pathCenterX = (minX + maxX) / 2;
        setCenter(pathCenterX, topNode.position.y + 800, { duration: 1000, zoom: 0.35 }).then(() => {
          zoomLock.value = true;
        }).then(() => {
          zoomLock.value = true
          watch(
            () => viewport.value.zoom,
            (newVal, oldVal) => {
              if (newVal && oldVal && zoomLock.value) {
                if (newVal > oldVal) {
                  setZoomLevel('in', newVal)
                } else if (newVal < oldVal) {
                  setZoomLevel('out', newVal)
                }
              }
            },
            { deep: true }
          );
        });
      })
    }, 300)
  }
  if (store.state.user == store.state.lpuserpathrelation.user_id) {
    await store.dispatch('updateUserPathRelation', {
      lpuserpathid: store.state.lpuserpathrelation.id,
    });
  }
})

const handleZoomLock = (node) => {
  nextTick(() => {
    let event = {
      node: null,
    }
    event.node = findNode(node)
    zoomstep.value = onNodeClick(event, zoomLock, setCenter)
  })
}

const setZoomLevel = async (action) => {
  zoomLock.value = false
  const oldViewport = viewport.value.zoom
  let newViewport = null
  let currentStepIndex = zoomSteps.findIndex(step => oldViewport < step);
  if (currentStepIndex === -1) {
    currentStepIndex = zoomSteps.length;
  }
  if (action === 'in') {
    if (currentStepIndex < zoomSteps.length) {
      newViewport = zoomSteps[currentStepIndex];
    } else {
      newViewport = zoomSteps[currentStepIndex - 1]
    }
  } else if (action === 'out') {
    if (currentStepIndex > 0) {
      newViewport = zoomSteps[currentStepIndex - 1];
    } else {
      newViewport = zoomSteps[zoomSteps.length - 2]
    }
  }
  if (newViewport == 0.2) {
    edges.value = outerGraphDisplay(edges.value, findNode, addEdges)
    setTimeout(() => {
      drawModules(user_learningpath.value, addNodes, removeNodes, findNode)
    }, 50);
  } else if (oldViewport < 0.25) {

    edges.value = innerGraphDisplay(edges.value, removeEdges)
    setTimeout(() => {
      drawModules(user_learningpath.value, addNodes, removeNodes, findNode)
    }, 50);
  }
  if (newViewport != undefined) {
    zoomstep.value = newViewport

    await zoomTo(newViewport, { duration: 500}).then(() => {
      zoomLock.value = true
    })
  }
}
// Watch for changes in the nodes
watch(() => user_learningpath.value, () => {
  setFlowchart()
}, { deep: true } )

// Set flowchart
function setFlowchart() {
  const flowchart = user_learningpath.value.json
  nodes.value = flowchart.tree.nodes;
  edges.value = innerGraphDisplay(flowchart.tree.edges, removeEdges);
  edges.value.forEach((edge) => {
    edge.deletable = false
    edge.type = 'custom'
  })
  setTimeout(() => {
    drawModules(user_learningpath.value, addNodes, removeNodes, findNode)
  }, 100);
}

// Zoom in node
function onNodeClickCall(event) {
  zoomstep.value = onNodeClick(event, zoomLock, setCenter)
  edges.value = innerGraphDisplay(edges.value, removeEdges)
}

</script>

<style>

.control-btns {
  height: 100px;
}
</style>