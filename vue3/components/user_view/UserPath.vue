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
        <i class="fa fa-arrow-left" /> Go Back to Overview
      </button>
      <h2 
        v-if="store.state.view!='student'"
        class="mt-3"
      >
        User path for:
      </h2>
      <div class="card">
        <div v-if="store.state.lpuserpathrelation && store.state.view!='student'">
          <div class="card-body">
            <h5 class="card-title">
              <i class="fa fa-user-circle" /> {{ store.state.lpuserpathrelation.username }}
            </h5>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">
                <i class="fa fa-user" /> Firstname: {{ store.state.lpuserpathrelation.firstname }}
              </li>
              <li class="list-group-item">
                <i class="fa fa-user" /> Lastname: {{ store.state.lpuserpathrelation.lastname }}
              </li>
              <li class="list-group-item">
                <i class="fa fa-envelope" /> Email: {{ store.state.lpuserpathrelation.email }}
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
            class="learning-path-flow"
          >
            <template #node-custom="{ data }">
              <CustomNodeEdit :data="data" />
            </template>
            <template #node-orcourses="{ data }">
              <CustomNodeEdit :data="data" />
            </template>
            <template #node-module="{ data }">
              <ModuleNode :data="data" />
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
import { onMounted, ref, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router'
import { useStore } from 'vuex';
import { VueFlow, useVueFlow } from '@vue-flow/core'
import CustomNodeEdit from '../nodes/CustomNodeEdit.vue'
import ModuleNode from '../nodes/ModuleNode.vue'
import Controls from '../user_view/UserControls.vue'
import drawModules from '../../composables/nodesHelper/drawModules'

// Load Router
const router = useRouter()
const route = useRoute()
// Load Store 
const store = useStore()

const { fitView, addNodes, removeNodes, findNode } = useVueFlow()
  
// Function to go back
const goBack = () => {
  router.go(-1) // Go back one step in the history
}

// Declare reactive variable for nodes
const nodes = ref([]);
const edges = ref([]);
const viewport = ref({});
const learningpath = ref(null)

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
  learningpath.value = await store.dispatch('fetchUserPathRelation', params)
})
// Watch for changes in the nodes
watch(
  () => store.state.lpuserpathrelation,
  () => {
    const flowchart = JSON.parse(store.state.lpuserpathrelation.json)
    nodes.value = flowchart.tree.nodes;
    edges.value = flowchart.tree.edges;
    edges.value.forEach((edge) => {
      edge.deletable = false
    })
    viewport.value = flowchart.tree.viewport;
    setTimeout(() => {
      fitView({ duration: 1000, padding: 0.5 });
    }, 100);    
  },
  { deep: true } // Enable deep watching to capture changes in nested properties
);

watch(() => learningpath.value, () => {
  const flowchart = learningpath.value.json
  nodes.value = flowchart.tree.nodes;
  edges.value = flowchart.tree.edges;
  edges.value.forEach((edge) => {
    edge.deletable = false
  })
  viewport.value = flowchart.tree.viewport;
  setTimeout(() => {
    fitView({ duration: 1000, padding: 0.5 });
    drawModules(learningpath.value, addNodes, removeNodes, findNode)
  }, 100);   
}, { deep: true } )
</script>