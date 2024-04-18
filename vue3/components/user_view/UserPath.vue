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
      <div class="card">
        <div v-if="userLearningpath && store.state.view!='student'">
          <div class="card-body">
            <h5 class="card-title">
              <i class="fa fa-user-circle" /> {{ userLearningpath.username }}
            </h5>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">
                <i class="fa fa-user" /> {{ store.state.strings.user_view_firstname }}: {{ userLearningpath.firstname }}
              </li>
              <li class="list-group-item">
                <i class="fa fa-user" /> {{ store.state.strings.user_view_lastname }}: {{ userLearningpath.lastname }}
              </li>
              <li class="list-group-item">
                <i class="fa fa-envelope" /> {{ store.state.strings.user_view_email }}: {{ userLearningpath.email }}
              </li>
            </ul>
          </div>
        </div>
        <div style="width: 100%; height: 600px;">
          <VueFlow 
            :nodes="nodes" 
            :edges="edges" 
            :viewport="viewport" 
            :default-viewport="viewport" 
            :max-zoom="1.5" 
            :min-zoom="0.2"
            :zoom-on-scroll="zoomLock"
            class="learning-path-flow"
            @edge-click="handleEdgeClicked"
            @node-click="onNodeClick"
          >
            <template #node-custom="{ data }">
              <CustomNodeEdit 
                :data="data" 
                :learningpath="userLearningpath"
              />
            </template>
            <template 
              #node-orcourses="{ data }"
            >
              <CustomStagNodeEdit 
                :data="data" 
                :learningpath="userLearningpath"
              />
            </template>
            <template #node-module="{ data }">
              <ModuleNode :data="data" />
            </template>
            <template #node-expandedcourses="{ data }">
              <ExpandNodeEdit 
                :data="data"
              />
            </template>
            <template #edge-custom="props">
              <TransitionEdge 
                v-bind="props" 
                @end-transition="handleZoomLock"
              />
            </template>
          </VueFlow>
        </div>
        <div 
          v-if="store.state.view != 'student'"
          class="d-flex justify-content-center"
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
import { useRouter, useRoute } from 'vue-router'
import { useStore } from 'vuex';
import { VueFlow, useVueFlow } from '@vue-flow/core'
import TransitionEdge from '../edges/TransitionEdge.vue'
import CustomNodeEdit from '../nodes/CustomNodeEdit.vue'
import CustomStagNodeEdit from '../nodes/CustomStagNodeEdit.vue'
import ExpandNodeEdit from '../nodes/ExpandNodeEdit.vue'
import ModuleNode from '../nodes/ModuleNode.vue'
import Controls from '../user_view/UserControls.vue'
import drawModules from '../../composables/nodesHelper/drawModules'

// Load Router
const router = useRouter()
const route = useRoute()
// Load Store 
const store = useStore()

const { fitView, addNodes, removeNodes, findNode, zoomTo, viewport, setCenter } = useVueFlow()
  
// Function to go back
const goBack = () => {
  router.go(-1) // Go back one step in the history
}

// Declare reactive variable for nodes
const nodes = ref([]);
const edges = ref([]);
const userLearningpath = ref(null)

const zoomSteps = [ 0.2, 0.35, 0.7, 1.5]
const zoomLock = ref(false)

onMounted( async () => {
  let params = []
  if (store.state.view == 'student') {
    params = {
      learningpathId: store.state.learningPathID,
      userId: store.state.user,
    }
  }else {
    params = route.params
  }
  userLearningpath.value = await store.dispatch('fetchUserPathRelation', params)
  setTimeout(() => {
    nextTick().then(() => {
      fitView({ duration: 1000, padding: 0.5 }).then(() => {
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
})

const handleZoomLock = () => {
  zoomLock.value = true
}

const setZoomLevel = async (action) => {
  zoomLock.value = false
  let newViewport = viewport.value.zoom
  let currentStepIndex = zoomSteps.findIndex(step => newViewport < step);
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
  if (newViewport != undefined) {
    await zoomTo(newViewport, { duration: 500}).then(() => {
      zoomLock.value = true
    })
  }
}
// Watch for changes in the nodes
watch(() => userLearningpath.value, () => {
  const flowchart = userLearningpath.value.json
  nodes.value = flowchart.tree.nodes;
  edges.value = flowchart.tree.edges;
  edges.value.forEach((edge) => {
    edge.deletable = false
    edge.type = 'custom'
  })
  setTimeout(() => {
    fitView({ duration: 1000, padding: 0.5 });
    drawModules(userLearningpath.value, addNodes, removeNodes, findNode)
  }, 100);   
}, { deep: true } )

// Zoom in node
function onNodeClick(event) {
  zoomLock.value = false
  setCenter( 
    event.node.position.x + event.node.dimensions.width/2, 
    event.node.position.y + event.node.dimensions.height/2,
    { zoom: 1, duration: 500}
  ).then(() => {
    zoomLock.value = true
  })
}

</script>

<style>
.vue-flow__edges {
  z-index: 2 !important;
}
</style>