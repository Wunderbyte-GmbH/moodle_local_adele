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
 
<script setup>
// Import needed libraries
import { Panel, useVueFlow } from '@vue-flow/core'
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import { nextTick, onMounted, watch, ref } from 'vue';
import { notify } from "@kyvg/vue3-notification";
import loadFlowChart from '../../composables/loadFlowChart';
import setStartingNode from '../../composables/setStartingNode';
import removeDropzones from '../../composables/removeDropzones';
import drawModules from '../../composables/nodesHelper/drawModules'
import removeModules from '../../composables/nodesHelper/removeModules';
import standaloneNodeCheck from '../../composables/standaloneNodeCheck';
import recalculateParentChild from '../../composables/recalculateParentChild';

// Load Store and Router
const store = useStore();
const router = useRouter();
const learningpathcontrol = ref({})

const { toObject, setNodes, setEdges, onPaneReady, removeNodes,
  addNodes, nodes, fitView, findNode } = useVueFlow()

// Define props in the setup block
const props = defineProps({
  learningpath: {
    type: Object,
    default: null,
  }
}); 

const showCacelConfirmation = ref(false)

// Emit to parent component
const emit = defineEmits(['change-class']);
// Toggle the dark mode of the flow-chart
function toggleClass() {
  emit('change-class');
}

// Watch for changes of the learning path
watch(() => props.learningpath, (newValue) => {
  learningpathcontrol.value = props.learningpath
  
  if (newValue.json.tree != undefined) {
    if(store.state.view == 'teacher'){
      newValue.json.tree.nodes.forEach((node) => {
        node.draggable = false
      })
    }

    setNodes(newValue.json.tree.nodes)
    setEdges(newValue.json.tree.edges)
  }else{
    setNodes([])
    setEdges([])
  }
  setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 800, store.state.view)
});

watch(() => props.learningpath.json.tree, () => {
  if (props.learningpath.json.tree != undefined) {
    loadFlowChart(props.learningpath.json.tree, store.state.view)
    drawModules(props.learningpath, addNodes, removeNodes, fitView)
  }
}, { deep: true } )

// Trigger web services on mount
onMounted( async () => {
  learningpathcontrol.value = props.learningpath
  if (learningpathcontrol.value.json.tree != undefined) {
    loadFlowChart(learningpathcontrol.value.json.tree, store.state.view)
    let nodesDimensions = []
    learningpathcontrol.value.json.tree.nodes.forEach((node) => {
      nodesDimensions.push(findNode(node.id))
    })
    learningpathcontrol.value.json.tree.nodes = nodesDimensions
    drawModules(learningpathcontrol.value, addNodes, removeNodes, fitView)
  }
  setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 800, store.state.view)
});


// Prepare and save learning path
const onSave = async () => {
    if (!learningpathcontrol.value.name || !learningpathcontrol.value.description) {
      notify({
        title: 'Saved failed',
        text: 'Provide a title and a short description for the learning path',
        type: 'error'
      });
    } else {
      removeNodes(['starting_node'])
      let tree = {};
      tree = toObject();
      tree = await removeModules(tree, null)
      tree = removeDropzones(tree)
      const singleNodes = standaloneNodeCheck(tree)
      if (singleNodes) {
        notify({
          title: 'Invalid Path',
          text: 'Found standalone nodes. Every node must be connected to the path',
          type: 'error'
        });
      } else {
        tree = recalculateParentChild(tree, 'parentCourse', 'childCourse', 'starting_node')
        learningpathcontrol.value.json.tree = tree
        store.dispatch('saveLearningpath', learningpathcontrol.value);
        store.dispatch('fetchLearningpaths');
        onCancelConfirmation()
        notify({
          title: store.state.strings.title_save,
          text: store.state.strings.description_save,
          type: 'success'
        });
      }
    }
};

// Cancel learning path edition and return to overview
const onCancel = () => {
  showCacelConfirmation.value = !showCacelConfirmation.value
};

const onCancelConfirmation = () => {
    store.state.learningpath = null;
    store.state.learningPathID = 0;
    store.state.editingadding = false;
    store.state.editingrestriction = false;
    store.state.editingpretest = false;
    router.push({name: 'learningpaths-edit-overview'});
};

// Fit pane into view
onPaneReady(({ fitView,}) => {
  fitView({ padding: 0.2 })
})

// Update the position of the nodes
function updatePos() {
  let elements = toObject();
  let loop = true
  //get all ids
  let nodelabels = ['starting_node'];
  let yvalue = 0;

  while (loop) {
    let newlabels = []
    let xvalue = 0;
    elements.nodes.forEach((el) => {
      nodelabels.forEach((label) => {
          if(el.parentCourse && el.parentCourse.includes(label)){
            el.position.y = yvalue
            el.position.x = xvalue
            xvalue -= 500;
            newlabels.push(el.id)
          }
      })
    })
    yvalue += 350;
    newlabels == [...new Set(newlabels)]
    nodelabels = newlabels
    if (nodelabels.length == 0 ) {
      loop = false
    }
  }
  setNodes(elements.nodes)
  setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 800, store.state.view)
}

</script>

<template>
  <Panel 
    v-if="store.state.view != 'teacher'"
    class="save-restore-controls"
  >
    <button 
      id="save-learning-path"
      class="btn btn-primary m-2" 
      @click="onSave"
    >
      {{ store.state.strings.save }}
    </button>
    <button 
      id="cancel-learning-path"
      class="btn btn-secondary m-2" 
      :disabled="showCacelConfirmation"
      @click="onCancel"
    >
      {{ store.state.strings.btncancel }}
    </button>
    <div 
      v-if="showCacelConfirmation"
      class="cancelConfi"
    >
      All unsaved changes will be lost
      <button 
        id="cancel-learning-path"
        class="btn btn-primary m-2" 
        @click="onCancel"
      >
        Back
      </button>
      <button 
        id="confim-cancel-learning-path"
        class="btn btn-warning m-2"
        @click="onCancelConfirmation"
      >
        Cancel
      </button>
    </div>
    <button 
      id="update-learning-path-position"
      class="btn btn-info m-2" 
      @click="updatePos"
    >
      {{ store.state.strings.btnupdate_positions }}
    </button>
    <button 
      class="btn btn-warning m-2" 
      @click="toggleClass"
    >
      {{ store.state.strings.btntoggle }}
    </button>
    <a 
      href="/backup/restorefile.php?contextid=1" 
      target="_blank" 
      rel="noreferrer noopener"
    >
      <button 
        class="btn btn-link" 
        :title="store.state.strings.btncreatecourse"
      >
        {{ store.state.strings.btncreatecourse }}
      </button>
    </a>
  </Panel>
</template>

<style scoped>
.cancelConfi{
  position: absolute;
  background-color: lightgray;
  border-radius: 0.5rem;
  padding: 0.25rem;
  margin: 0.25rem;
}
</style>