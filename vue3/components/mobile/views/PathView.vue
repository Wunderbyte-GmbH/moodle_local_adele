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
    <div style="width: 100%; height: 600px;">
      <VueFlow
        :nodes="nodes"
        :edges="edges"
        @node-click="onNodeClick"
      >
        <template #node-custom="{ data }">
          <MobileNode
            :data="data"
          />
        </template>
        <template #node-orcourses="{ data }">
          <MobileNode
            :data="data"
          />
        </template>
      </VueFlow>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useStore } from 'vuex'
import { VueFlow, useVueFlow } from '@vue-flow/core'
import MobileNode from '../../nodes/MobileNode.vue'

const store = useStore()
const { fitView} = useVueFlow()

const nodes = ref([]);
const edges = ref([]);
let clickTimeout = null;

onMounted(() => {
  if (store.state.lpuserpathrelation.json) {
    setFlowchart()
  }
})

// Emit to parent component
const emit = defineEmits([
  'changed-details',
]);


// Set flowchart
function setFlowchart() {
  nodes.value = store.state.lpuserpathrelation.json.tree.nodes
  edges.value = store.state.lpuserpathrelation.json.tree.edges
  edges.value.forEach((edge) => {
    edge.deletable = false
    edge.type = 'smoothstep'
  })
  setTimeout(() => {
    fitView({ duration: 1000 })
  }, 100)
}

const onNodeClick = (event) => {
  if (clickTimeout) {
    clearTimeout(clickTimeout)
    clickTimeout = null
    onNodeDoubleClick(event.node.id)
  } else {
    clickTimeout = setTimeout(() => {
      clickTimeout = null
    }, 1000) // Adjust the timeout duration as needed
  }
}

const onNodeDoubleClick = (node_id) => {
  emit('changed-details', node_id);
}


</script>
