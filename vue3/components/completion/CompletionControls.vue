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
import validateNodes from '../../composables/validateNodes';
import validateQuizGlobal from '../../composables/validateQuizGlobal';
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
    required: true,
  },
  learningpath: {
    type: Object,
    required: true,
  },
});

const learningpathCompletion = ref(null)
const copieLearningpathCompletion = ref({})
const showCancelConfirmation = ref(false)
const showedWarning = ref(false)
const lastGlobalMissing = ref(0)

onMounted(() => {
  learningpathCompletion.value = props.learningpath
  copieLearningpathCompletion.value = JSON.parse(JSON.stringify(props.learningpath))
})

const {
  onPaneReady, toObject, setNodes,
  setEdges, findNode
} = useVueFlow()

// Emit to parent component
const emit = defineEmits([
  'change-class'
]);
// Toggle the dark mode of the flow-chart
function toggleClass() {
  emit('change-class');
}

const stopWatcher = watch(() => learningpathCompletion.value, async () => {
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
  stopWatcher()
}, { deep: true } );

// Prepare and save learning path
const onSave = async () => {
  const conditions = perpareCompletion()
  const singleNodes = standaloneNodeCheck(conditions)
  if (singleNodes) {
    notify({
        title: store.state.strings.completion_invalid_path_title,
        text: store.state.strings.completion_invalid_path_text,
        type: 'error'
      });
      return;
  }
  const invalidNodes = validateNodes(conditions, findNode)
  if (invalidNodes) {
    notify({
      title: store.state.strings.completion_invalid_condition_title,
      text: store.state.strings.completion_invalid_condition_text,
      type: 'error'
    });
    return;
  }
  const invalidGlobalScale = validateQuizGlobal(conditions, findNode, store.state.quizsetting)
  if (invalidGlobalScale != lastGlobalMissing.value) {
    lastGlobalMissing.value = invalidGlobalScale
    showedWarning.value = false
  }
  if (
    invalidGlobalScale && !showedWarning.value) {
    notify({
      title: store.state.strings.completion_empty_global_value,
      text: store.state.strings.completion_empty_global_value_text,
      type: 'warn'
    });
    showedWarning.value = true
    lastGlobalMissing.value = invalidGlobalScale
    return;
  }

  conditions.nodes.forEach((node) => {
    delete(node.data.error)
  })

  //save learning path
  learningpathCompletion.value.json.tree.nodes = learningpathCompletion.value.json.tree.nodes
    .filter(element_node => element_node.type !== 'dropzone')
    .map(element_node => {
      if (element_node.id === store.state.node.node_id) {
        return { ...element_node, [props.condition]: conditions };
      }
      return element_node;
  });
  const learningpathID = await store.dispatch('saveLearningpath', learningpathCompletion.value)
  router.push('/learningpaths/edit/' + learningpathID);
  onCancelConfirmation(true);
  notify({
    title: store.state.strings.title_save,
    text: store.state.strings.description_save,
    type: 'success'
  })
};

const perpareCompletion = () => {
  let condition = toObject();
  condition = removeDropzones(condition)
  return recalculateParentChild(condition, 'parentCondition', 'childCondition', 'starting_condition')
}

// Cancel learning path edition and return to overview
const onCancel = () => {
  const condition = perpareCompletion()
  learningpathCompletion.value.json.tree.nodes.forEach(element_node => {
    if (
      store.state.node &&
      element_node.id === store.state.node.node_id
    ) {
        if (
          element_node[props.condition] == undefined &&
          condition.nodes.length == 0
        ) {
          onCancelConfirmation(false)
        }
        if (
          element_node[props.condition] &&
          JSON.stringify(condition.nodes) == JSON.stringify(element_node[props.condition].nodes)
        ) {
          onCancelConfirmation(false)
        } else {
          showCancelConfirmation.value = !showCancelConfirmation.value
        }
      }
  });
};

const onCancelConfirmation = (toggle) => {
  if (toggle) {
    onCancel()
  }
  store.state.editingpretest = false
  store.state.editingrestriction = false
  store.state.editingadding = true
  store.state.node = null
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