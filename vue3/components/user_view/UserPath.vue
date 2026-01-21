<template>
  <div>
    <notifications width="100%" />
    <div>
      <button
        v-if="store.state.view!='student'"
        class="btn btn-outline-primary"
        @click="goBack"
      >
        <i class="fas fa-arrow-left" /> {{ store.state.strings.user_view_go_back_overview }}
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
                :class="store.state.version ? 'fas fa-user-circle' : 'fas fa-user'"
              />
              {{ user_learningpath.username }}
            </h5>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">
                <i class="fas fa-user" /> {{ store.state.strings.user_view_firstname }}: {{ user_learningpath.firstname }}
              </li>
              <li class="list-group-item">
                <i class="fas fa-user" /> {{ store.state.strings.user_view_lastname }}: {{ user_learningpath.lastname }}
              </li>
              <li class="list-group-item">
                <i class="fas fa-envelope" /> {{ store.state.strings.user_view_email }}: {{ user_learningpath.email }}
              </li>
            </ul>
          </div>
        </div>
        <div
          style="width: 100%; height: 600px;"
          @wheel="onWheel($event, zoomLockVaraible, viewport, zoomTo)"
        >
          <VueFlow
            :nodes="nodes"
            :edges="edges"
            :viewport="viewport"
            :default-viewport="viewport"
            :max-zoom="1.55"
            :min-zoom="0.15"
            :zoom-on-scroll="false"
            :zoom-on-pinch="false"
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
                @expanding-cards="handleExpandCards"
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
import onNodeClick from '../../composables/flowHelper/onNodeClick';
import onWheel from '../../composables/flowHelper/onWheel';
import ExpandedCourses from '../nodes_items/ExpandedCourses.vue';

// Load Router
const router = useRouter()
const route = useRoute()

// Load Store
const store = useStore()

const {
  addNodes, removeNodes, findNode,
  zoomTo, viewport, setCenter
} = useVueFlow()

const goBack = () => {
  router.go(-1)
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
const zoomstep = ref(0)
const zoomLockVaraible = ref(false)
const user_learningpath = ref({})

onMounted( async () => {
  if (!store.state.availablecourses) {
    store.dispatch('fetchAvailablecourses')
  }
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
        setCenter(pathCenterX, topNode.position.y + 800, { duration: 1000, zoom: 0.35 })
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
    if (event.node) {
      zoomstep.value = onNodeClick(event, setCenter, store)
    }
  })
}

const handleExpandCards = async () => {
    await zoomTo(0.35, { duration: 500})
}

watch(() => user_learningpath.value, () => {
  setFlowchart()
}, { deep: true } )

// Set flowchart
function setFlowchart() {
  const flowchart = user_learningpath.value.json
  nodes.value = flowchart.tree.nodes;
  edges.value = flowchart.tree.edges;

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
  zoomstep.value = onNodeClick(event, setCenter, store);
  // Find all expand buttons and click if they are ExpandedCourses.
  nextTick(() => {
    // Query all elements with the 'fa-minus-circle' class
    const buttons = document.querySelectorAll('.fa-minus-circle');
    
    // For each found button, simulate a click event
    buttons.forEach(button => {
        button.click();
    });
  });
}

</script>

<style>
.vue-flow__edge-layer {
  z-index: 0; /* Ensure edges are below */
}

.vue-flow__node-layer {
  z-index: 10; /* Ensure nodes are above */
}

.control-btns {
  height: 100px;
}
</style>

<style scoped>
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/theme-default.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';

.learning-path-flow {
  border-radius: 1rem;
}
</style>