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
import { Handle, Position } from '@vue-flow/core'
import { defineProps, computed  } from 'vue';
import { useStore } from 'vuex';
import CompletionItem from '../completion/CompletionItem.vue'

// Load Store 
const store = useStore();
const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

// Set the node that handle has been clicked
const setStartNode = (node_id) => {
  store.commit('setstartNode', {
    startnode: node_id, 
  });
};

// Connection handles
const sourceHandleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))
const targetHandleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

</script>

<template>
  <div class="custom-node text-center rounded p-3"
    style="height: 200px; width: 400px;">
    <div class="mb-2"><b>{{ store.state.strings.node_coursefullname }}</b> {{ data.fullname }}</div>
    <div class="mb-2"><b>{{ store.state.strings.node_courseshortname }}</b> {{ data.shortname }}</div>
    <div v-if="data.manual">
      <CompletionItem :completion="data" />
    </div>
    <div>
    </div>
  </div>
  <Handle id="target" type="target" :position="Position.Top" :style="targetHandleStyle" @mousedown="() => setStartNode(data.node_id)"/>
  <Handle id="source" type="source" :position="Position.Bottom" :style="sourceHandleStyle" @mousedown="() => setStartNode(data.node_id)"/>
</template>

<style scoped>
.custom-node {
  background-color: white;
  padding: 10px;
  border: 1px solid #ccc;
}

</style>