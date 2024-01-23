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
import OverviewRestrictionCompletion from '../nodes_items/OverviewRestrictionCompletion.vue';

// Load Store 
const store = useStore();
const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

// Set node data for the modal
const setNodeModal = () => {
  store.state.node = props.data
};

// Set node data for the modal
const setPretestView = () => {
  store.state.node = props.data
  store.state.editingpretest = true
  store.state.editingadding = false
  store.state.editingrestriction = false
};

// Set node data for the modal
const setRestrictionView = () => {
  store.state.node = props.data
  store.state.editingpretest = false
  store.state.editingadding = false
  store.state.editingrestriction = true
};

// Set the node that handle has been clicked
const setStartNode = (node_id) => {
  store.commit('setstartNode', {
    startnode: node_id, 
  });
};

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

</script>

<template>
  <div>
    <div 
      class="custom-node text-center rounded p-3" 
      style="height: 200px; width: 400px;"
    >
      <div v-if="store.state.view!='teacher'">
        <button 
          type="button" 
          class="btn btn-secondary" 
          @click="setRestrictionView"
        >
          <i class="fa fa-cogs" /> Edit Restrictions
        </button>
      </div>
      <div class="mb-2">
        <strong>{{ store.state.strings.node_coursefullname }}</strong> {{ data.fullname }}
      </div>
      <div v-if="store.state.view!='teacher'">
        <button 
          type="button" 
          class="btn btn-primary" 
          data-toggle="modal" 
          data-target="#nodeModal"
          @click="setNodeModal"
        >
          <i class="fa fa-edit" /> {{ store.state.strings.edit_course_node }}
        </button>
        <button 
          type="button" 
          class="btn btn-secondary" 
          @click="setPretestView"
        >
          <i class="fa fa-tasks" /> {{ store.state.strings.edit_node_pretest }}
        </button>
      </div>
      <OverviewRestrictionCompletion :node="data" />
    </div>
    <Handle 
      id="target" 
      type="target" 
      :position="Position.Top" 
      :style="handleStyle" 
      @mousedown="() => setStartNode(data.node_id)" 
    />
    <Handle 
      id="source" 
      type="source" 
      :position="Position.Bottom" 
      :style="handleStyle" 
      @mousedown="() => setStartNode(data.node_id)" 
    />
  </div>
</template>

<style scoped>
.custom-node {
  position: relative;
  background-color: white;
  padding: 10px;
  border: 1px solid #ccc;
}
.card-text {
  padding: 5px;
}

</style>