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
import { nextTick, onMounted, watch } from 'vue';
import { notify } from "@kyvg/vue3-notification";
import loadFlowChart from '../../composables/loadFlowChart';
import setStartingNode from '../../composables/setStartingNode';
import removeDropzones from '../../composables/removeDropzones';
import standaloneNodeCheck from '../../composables/standaloneNodeCheck';
import recalculateParentChild from '../../composables/recalculateParentChild';


// Load Store and Router
const store = useStore();
const router = useRouter();

const { toObject, setNodes, setEdges, onPaneReady, removeNodes,
  addNodes, nodes } = useVueFlow()

// Define props in the setup block
const props = defineProps({
  learninggoal: {
    type: Object,
    default: null,
  }
}); 

// Emit to parent component
const emit = defineEmits(['change-class']);
// Toggle the dark mode of the flow-chart
function toggleClass() {
  emit('change-class');
}

// Watch for changes of the learning path
watch(() => store.state.learninggoal[0], (newValue) => {
  if (newValue.json.tree != undefined) {
    //loadFlowChart(newValue.json.tree, store.state.view)
    setNodes(newValue.json.tree.nodes)
    setEdges(newValue.json.tree.edges)
  }else{
    setNodes([])
    setEdges([])
  }
  setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 800, store.state.view)
});

// Trigger web services on mount
onMounted(() => {
  setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 800, store.state.view)
});

// Watch for changes of the learning path
if (store.state.learninggoal[0].json.tree != undefined) {
  loadFlowChart(store.state.learninggoal[0].json.tree, store.state.view)
}

// Prepare and save learning path
const onSave = () => {
    removeNodes(['starting_node'])
    let obj = {};
    obj['tree'] = toObject();
    obj['tree'] = removeDropzones(obj['tree'])
    const singleNodes = standaloneNodeCheck(obj['tree'])
    if (singleNodes) {
      notify({
        title: 'Invalid Path',
        text: 'Found standalone nodes. Every node must be connected to the path',
        type: 'error'
      });
    } else {
      obj['tree'] = recalculateParentChild(obj['tree'], 'parentCourse', 'childCourse', 'starting_node')
      obj = JSON.stringify(obj);
      let result = {
          learninggoalid: props.learninggoal.id,
          name: props.learninggoal.name,
          description: props.learninggoal.description,
          json: obj,
      };
      store.dispatch('saveLearningpath', result);
      store.dispatch('fetchLearningpaths');
      store.state.learningGoalID = 0;
      store.state.editingadding = false;
      router.push({name: 'learninggoals-edit-overview'});
      window.scrollTo(0,0);
  
      notify({
        title: store.state.strings.title_save,
        text: store.state.strings.description_save,
        type: 'success'
      });
    }
};

// Cancel learning path edition and return to overview
const onCancel = () => {
    store.state.learningGoalID = 0;
    store.state.editingadding = false;
    store.state.editingrestriction = false;
    router.push({name: 'learninggoals-edit-overview'});
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
          if(el.parentCourse.includes(label)){
            el.position.y = yvalue
            el.position.x = xvalue
            xvalue -= 500;
            newlabels.push(el.id)
          }
      })
    })
    yvalue += 600;
    newlabels == [...new Set(newlabels)]
    nodelabels = newlabels
    console.log(nodelabels)
    if (nodelabels.length == 0 ) {
      loop = false
    }
  }
  setNodes(elements.nodes)
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
      @click="onCancel"
    >
      {{ store.state.strings.btncancel }}
    </button>
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
