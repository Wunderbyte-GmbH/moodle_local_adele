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
import { useRoute, useRouter } from 'vue-router';
import removeModules from '../../composables/nodesHelper/removeModules';
import { ref } from 'vue';

// Load Store and Router
const store = useStore();
const router = useRouter()
const route = useRoute()
const { toObject } = useVueFlow()

const showCancelConfirmation = ref(false)

 // Prepare and save learning path
 const onSave = async () => {
    let completion = toObject();
    const route_params = route.params;
    completion = await removeModules(completion, null)
    await store.dispatch('saveUserPathRelation', {
        nodes: completion.nodes,
        route: route_params});
    router.push({
      path: '/learningpaths/edit/',
      query: {notify: true}
    })
 };

 // Cancel learning path edition and return to overview
const onCancel = () => {
  showCancelConfirmation.value = !showCancelConfirmation.value
};

const onCancelConfirmation = () => {
  router.go(-1)
};

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
        @click="onCancelConfirmation"
      >
        {{ store.state.strings.flowchart_cancel_button }}
      </button>
    </div>
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