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
import { onMounted, ref } from 'vue';

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

const learningpathRestriction = ref(null)

const showCancelConfirmation = ref(false)

onMounted(() => {
  learningpathRestriction.value = props.learningpath
})

const { onPaneReady, toObject, setNodes, setEdges } = useVueFlow()

// Emit to parent component
const emit = defineEmits(['']);
// Toggle the dark mode of the flow-chart
function toggleClass() {
  emit('change-class');
}

// Watch for changes of the learning path
if (store.state.node != undefined && learningpathRestriction.value.json != '') {
    let restriction = learningpathRestriction.value.json.tree.nodes.filter(node => {
      return node.id === store.state.node.node_id
    })
    const flowchart = loadFlowChart(restriction[0].restriction, store.state.view)
    setNodes(flowchart.nodes)
    setEdges(flowchart.edges)
}

// Prepare and save learning path
const onSave = async () => {
  const restriction = perpareCompletion()
  const singleNodes = standaloneNodeCheck(restriction)
  if (singleNodes) {
    notify({
        title: store.state.strings.restriction_invalid_path_title,
        text: store.state.strings.restriction_invalid_path_text,
        type: 'error'
      });
  } else{
    //save learning path
    learningpathRestriction.value.json.tree.nodes = learningpathRestriction.value.json.tree.nodes.map(element_node => {
        if (element_node.id === store.state.node.node_id) {
          return { ...element_node, restriction: restriction };
        }
        return element_node;
    });
    const learningpathID = await store.dispatch('saveLearningpath', learningpathRestriction.value)
    router.push('/learningpaths/edit/' + learningpathID);
    onCancelConfirmation(true);
    notify({
      title: store.state.strings.title_save,
      text: store.state.strings.description_save,
      type: 'success'
    })
  }
};

const perpareCompletion = () => {
  let restriction = toObject();
  restriction = removeDropzones(restriction)
  return recalculateParentChild(restriction, 'parentCondition', 'childCondition', 'starting_condition')
}

// Cancel learning path edition and return to overview
const onCancelConfirmation = (toggle) => {
  if (toggle) {
    onCancel()
  }
  store.state.editingrestriction = false
  store.state.editingadding = true
  store.state.node = null
};

// Cancel learning path edition and return to overview
const onCancel = () => {
  const restriction = perpareCompletion()
  learningpathRestriction.value.json.tree.nodes.forEach(element_node => {
    if (
      store.state.node &&
      element_node.id === store.state.node.node_id
    ) {
        if (JSON.stringify(restriction.nodes) == JSON.stringify(element_node.restriction.nodes)) {
          onCancelConfirmation(false)
        } else {
          showCancelConfirmation.value = !showCancelConfirmation.value
        }
      }
  });
};

// Fit pane into view
onPaneReady(({ fitView,}) => {
  fitView()
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
      :disabled="showCancelConfirmation"
      @click="onCancel"
    >
      {{ store.state.strings.btncancel }}
    </button>
    <div
      v-if="showCancelConfirmation"
      class="cancelConfi"
    >
      {{ store.state.strings.flowchart_cancel_confirmation }}
      <button
        id="cancel-learning-path"
        class="btn btn-primary m-2"
        @click="onCancel"
      >
        {{ store.state.strings.flowchart_back_button }}
      </button>
      <button
        id="confim-cancel-learning-path"
        class="btn btn-warning m-2"
        @click="onCancelConfirmation(true)"
      >
        {{ store.state.strings.flowchart_cancel_button }}
      </button>
    </div>
    <button
      class="btn btn-warning m-2"
      @click="toggleClass"
    >
      {{ store.state.strings.btntoggle }}
    </button>
  </Panel>
</template>

<style scoped>
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