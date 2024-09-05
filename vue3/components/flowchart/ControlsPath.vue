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
import { computed } from 'vue';

// Load Store and Router
const store = useStore();
const router = useRouter();
const learningpathcontrol = ref({})
const deepCopy = ref({})
const deepCopyText = ref({})

const { toObject, setNodes, setEdges, removeNodes,
  addNodes, nodes, findNode } = useVueFlow()

// Define props in the setup block
const props = defineProps({
  learningpath: {
    type: Object,
    default: null,
  },
  view: {
    type: Boolean,
    default: false,
  }
});

const showCancelConfirmation = ref(false)

const current_view = ref(true)
const current_user_view = ref(true)

// Emit to parent component
const emit = defineEmits([
  'change-class',
  'change-user-view',
  'finish-edit'
]);
// Toggle the dark mode of the flow-chart
function toggleClass() {
  current_view.value = !current_view.value
  emit('change-class');
}

function toggleUserView() {
  current_user_view.value = !current_user_view.value
  emit('change-user-view')
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
  if (!props.view) {
    setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 800, store)
  }
});

watch(() => learningpathcontrol.value, async() => {
  if (learningpathcontrol.value.json.tree != undefined) {
    await drawModules(learningpathcontrol.value, addNodes, removeNodes, findNode)
  }
}, { deep: true } )

// Trigger web services on mount
onMounted( async () => {
  learningpathcontrol.value = props.learningpath
  if (learningpathcontrol.value.json.tree != undefined) {
    const flowchart = loadFlowChart(props.learningpath.json.tree, store.state.view)
    setNodes(flowchart.nodes)
    setEdges(flowchart.edges)
    let nodesDimensions = []
    learningpathcontrol.value.json.tree.nodes.forEach((node) => {
      nodesDimensions.push(findNode(node.id))
    })
    learningpathcontrol.value.json.tree.nodes = nodesDimensions
    drawModules(learningpathcontrol.value, addNodes, removeNodes, findNode)
  }
  if (!props.view) {
    setStartingNode(removeNodes, nextTick, addNodes, nodes.value, 800, store)
    deepCopy.value = JSON.parse(JSON.stringify(toObject()))
    deepCopyText.value = JSON.parse(JSON.stringify(props.learningpath))
  }
});


// Prepare and save learning path
const onSave = async () => {
    if (!learningpathcontrol.value.name || !learningpathcontrol.value.description) {
      notify({
        title: store.state.strings.flowchart_save_notification_title,
        text: store.state.strings.flowchart_save_notification_text_missing_strings,
        type: 'error'
      });
    } else {
      removeNodes(['starting_node'])
      if (learningpathcontrol.value.id == 0) {
        if (learningpathcontrol.value.json != '') {
          learningpathcontrol.value.json.modules = store.state.modules
        }else {
          learningpathcontrol.value.json = { modules: store.state.modules}
        }
        store.state.modules = null;
      }
      let singleNodes = false
      if (learningpathcontrol.value.json.tree) {
        learningpathcontrol.value.json.tree = await removeModules(learningpathcontrol.value.json.tree, null)
        learningpathcontrol.value.json.tree = removeDropzones(learningpathcontrol.value.json.tree)
        singleNodes = standaloneNodeCheck(learningpathcontrol.value.json.tree)
        learningpathcontrol.value.json.tree =
          recalculateParentChild(learningpathcontrol.value.json.tree, 'parentCourse', 'childCourse', 'starting_node')
      }
      if (singleNodes) {
        notify({
          title: store.state.strings.flowchart_invalid_path_notification_title,
          text: store.state.strings.flowchart_save_notification_text,
          type: 'error'
        });
      } else {
        if (!learningpathcontrol.value.image) {
          learningpathcontrol.value.image = '';
        }
        await store.dispatch('saveLearningpath', learningpathcontrol.value);
        onCancelConfirmation(true)
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
  let finalpath = toObject()
  finalpath = finalpath.nodes.filter(object => object.id !== 'starting_node');
  if (
    deepCopyText.value.description == props.learningpath.description &&
    deepCopyText.value.name == props.learningpath.name &&
    JSON.stringify(finalpath) == JSON.stringify(deepCopy.value.nodes)
  ) {
    onCancelConfirmation(false)
  } else {
    showCancelConfirmation.value = !showCancelConfirmation.value
  }
};

const undoNodesLength = computed(() => store.state.undoNodes.length);

function undoDeletion() {
  store.commit('unsetUndoNodes');
}

const onCancelConfirmation = (toggle) => {
    if (toggle) {
      onCancel()
    }
    store.state.learningpath = null;
    store.state.learningPathID = 0;
    store.state.editingadding = false;
    store.state.editingrestriction = false;
    store.state.editingpretest = false;
    store.state.viewing = false;
    emit('finish-edit');
    router.push({name: 'learningpaths-edit-overview'});
};

</script>

<template>
  <Panel
    v-if="store.state.view != 'teacher' && !props.view"
    class="save-restore-controls"
    style="margin-top:3rem"
  >
    <button
      v-if="current_user_view"
      id="save-learning-path"
      class="btn btn-primary m-2"
      @click="onSave"
    >
      {{ store.state.strings.save }}
    </button>
    <button
      id="cancel-learning-path"
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
      {{ current_view ? store.state.strings.btndarktoggle : store.state.strings.btnlighttoggle }}
    </button>
    <button
      class="btn btn-info m-2"
      @click="toggleUserView"
    >
      {{ current_user_view ? store.state.strings.btnstudenttoggle : store.state.strings.btneditortoggle }}
    </button>
    <button
      v-if="undoNodesLength"
      class="btn btn-warning m-2"
      @click="undoDeletion"
    >
      Undo last node deletion
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
  <Panel
    v-else-if="props.view"
    class="save-restore-controls"
  >
    <button
      id="cancel-learning-path"
      class="btn btn-secondary m-2"
      :disabled="showCancelConfirmation"
      @click="onCancelConfirmation(false)"
    >
      {{ store.state.strings.btncancel }}
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