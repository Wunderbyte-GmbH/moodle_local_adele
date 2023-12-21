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
import { useRoute, useRouter } from 'vue-router';
import { nextTick } from 'vue';
 
 // Load Store and Router
 const store = useStore();
 const router = useRouter()
 const route = useRoute()
 const { toObject } = useVueFlow()
   
 // Prepare and save learning path
 const onSave = () => {
    const completion = toObject();
    const route_params = route.params;
    store.dispatch('saveUserPathRelation', { 
        nodes: completion.nodes, 
        route: route_params});

    setTimeout(() => {
      //store.dispatch('fetchUserPathRelation', route.params);

    }, 100);

    notify({
        title: store.state.strings.title_save,
        text: store.state.strings.description_save,
        type: 'success'
    });
 };
 
 // Cancel learning path edition and return to overview
 const onCancel = () => {
    router.go(-1) // Go back one step in the history
 };
 
 </script>
 
 <template>
   <Panel class="save-restore-controls">
     <button class="btn btn-primary m-2" @click="onSave">{{store.state.strings.save}}</button>
     <button class="btn btn-secondary m-2" @click="onCancel">{{store.state.strings.btncancel}}</button>
   </Panel>
 </template>
 