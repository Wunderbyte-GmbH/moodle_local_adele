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
import { notify } from "@kyvg/vue3-notification";
import loadFlowChart from '../../composables/loadFlowChart'
import removeDropzones from '../../composables/removeDropzones';
import standaloneNodeCheck from '../../composables/standaloneNodeCheck';
import recalculateParentChild from '../../composables/recalculateParentChild';
import { useRouter } from 'vue-router';
import { onMounted, ref, watch } from 'vue';

// Load Store and Router
const store = useStore();
const router = useRouter();

// Defined props from the parent component
const props = defineProps({
  condition: {
    type: String,
    default: null,
  },
  learningpath: {
    type: String,
    required: true,
  },
});

const learningpathCompletion = ref(null)

onMounted(() => {
  learningpathCompletion.value = props.learningpath
})

const { onPaneReady, toObject, setNodes, setEdges, findNode } = useVueFlow()

// Emit to parent component
const emit = defineEmits(['change-class']);
// Toggle the dark mode of the flow-chart
function toggleClass() {
  emit('change-class');
}

watch(() => learningpathCompletion.value, async () => {
  // Watch for changes of the learning path
  if (learningpathCompletion.value && learningpathCompletion.value.json.tree &&
    store.state.node != undefined && learningpathCompletion.value.json != '') {

      let condition = learningpathCompletion.value.json.tree.nodes.filter(node => {
        return node.id === store.state.node.node_id
      })
      const flowchart = loadFlowChart(condition[0][props.condition], store.state.view)
      if (flowchart) {
        setNodes(flowchart.nodes)
        setEdges(flowchart.edges)
      }
  }
}, { deep: true } );

// Prepare and save learning path
const onSave = async () => {
  let condition = toObject();
  condition = removeDropzones(condition)
  const singleNodes = standaloneNodeCheck(condition)
  if (singleNodes) {
    notify({
        title: store.state.strings.completion_invalid_path_title,
        text: store.state.strings.completion_invalid_path_text,
        type: 'error'
      });
  } else{
    condition = recalculateParentChild(condition, 'parentCondition', 'childCondition', 'starting_condition')
    //save learning path
    learningpathCompletion.value.json.tree.nodes = learningpathCompletion.value.json.tree.nodes.map(element_node => {
        if (element_node.id === store.state.node.node_id) {
          return { ...element_node, [props.condition]: condition };
        }
        return element_node;
    });
    const learningpathID = await store.dispatch('saveLearningpath', learningpathCompletion.value)
    router.push('/learningpaths/edit/' + learningpathID);
    onCancel();
    notify({
      title: store.state.strings.title_save,
      text: store.state.strings.description_save,
      type: 'success'
    })
  }
};

// Cancel learning path edition and return to overview
const onCancel = () => {
  store.state.editingpretest = false
  store.state.editingrestriction = false
  store.state.editingadding = true
  store.state.node = null
};

// Fit pane into view
onPaneReady(({ fitView,}) => {
  fitView({ padding: 0.2 })
})

</script>

<template>
  <Panel class="save-restore-controls">
    <button 
      class="btn btn-primary m-2" 
      @click="onSave"
    >
      {{ store.state.strings.save }}
    </button>
    <button 
      class="btn btn-secondary m-2" 
      @click="onCancel"
    >
      {{ store.state.strings.btncancel }}
    </button>
    <button 
      class="btn btn-warning m-2" 
      @click="toggleClass"
    >
      {{ store.state.strings.btntoggle }}
    </button>
  </Panel>
</template>
