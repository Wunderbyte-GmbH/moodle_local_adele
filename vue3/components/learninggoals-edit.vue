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

<style scoped>
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/theme-default.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';

.dndflow{flex-direction:column;display:flex;height:500px}.dndflow aside{color:#fff;font-weight:700;border-right:1px solid #eee;padding:15px 10px;font-size:12px;background:rgba(16,185,129,.75);-webkit-box-shadow:0px 5px 10px 0px rgba(0,0,0,.3);box-shadow:0 5px 10px #0000004d}.dndflow aside .nodes>*{margin-bottom:10px;cursor:grab;font-weight:500;-webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);box-shadow:5px 5px 10px 2px #00000040}.dndflow aside .description{margin-bottom:10px}.dndflow .vue-flow-wrapper{flex-grow:1;height:100%}@media screen and (min-width: 640px){.dndflow{flex-direction:row}.dndflow aside{min-width:25%}}@media screen and (max-width: 639px){.dndflow aside .nodes{display:flex;flex-direction:row;gap:5px}}
.learning-path-flow.dark{background:#4e574f;}
</style>
<template>
    <div>
      <notifications width="100%" />
        <div v-if="$store.state.editingadding == false && $store.state.editingpretest == false">
            <LearningPathList />
        </div>
        <div v-if="$store.state.editingadding == true">
          <h3>{{ store.state.strings.learninggoal_form_title_edit }}</h3>
          <div>
            <div v-for="goal in store.state.learninggoal">
              <p>
                <h4>{{ store.state.strings.fromlearningtitel }}</h4>
                <input
                  v-if="$store.state.learningGoalID == 0"
                  :placeholder="store.state.strings.goalnameplaceholder"
                  autofocus
                  type="text"
                  v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
                  v-model="goalname"
                />
                <input
                  v-else
                  type="text"
                  v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
                  v-model="goal.name"
                />
              </p>
              <p>
                <h4>{{ store.state.strings.fromlearningdescription }}</h4>
                <input
                  v-if="$store.state.learningGoalID == 0"
                  :placeholder="store.state.strings.goalsubjectplaceholder"
                  type="textarea"
                  v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
                  v-model="goaldescription"
                />
                <input
                  v-else
                  type="textarea"
                  v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
                  v-model="goal.description"
                />
              </p>
              <LearingPath />
            </div>
        </div>
      </div>
      <div v-if="$store.state.editingpretest == true">
          <Completion />
      </div>
  </div>
</template>

<script setup>
// Import needed libraries
import { ref, onMounted, watch, nextTick } from 'vue'
import { onBeforeRouteUpdate } from 'vue-router';
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import { notify } from "@kyvg/vue3-notification"
import Completion from './completion/Completion.vue'
import LearingPath from './flowchart/LearningPath.vue'
import LearningPathList from './LearningPathList.vue'

// Load Store and Router
const store = useStore()
const router = useRouter()

// Define constants that will be referenced
const goalname = ref('')
const goaldescription = ref('')
const clicked = ref({})

// Checking routes 
const checkRoute = (currentRoute) => {
    if(currentRoute == undefined){
        router.push({ name: 'learninggoals-edit-overview' });
    }
  else if (currentRoute.name === 'learninggoal-edit') {

    store.state.editingadding = true;
    nextTick(() => showForm(currentRoute.params.learninggoalId));
  } else if (currentRoute.name === 'learninggoal-new') {
    store.state.editingadding = true;
    nextTick(() => showForm(null));
  }
};

// Trigger web services on mount
onMounted(() => {
  store.dispatch('fetchLearningpaths');
  store.dispatch('fetchAvailablecourses');
  checkRoute(router.value);

});

// Showing form to generate or edit learning path
const showForm = async (learninggoalId = null) => {
  goalname.value = ''
  goaldescription.value = ''
  if (learninggoalId) {
    store.state.learningGoalID = learninggoalId;
    store.dispatch('fetchLearningpath')
    store.state.editingadding = true
    // Do something here in case of an edit.
  } else {
    store.dispatch('fetchLearningpath')
    store.state.editingadding = true
    // Do something here in case of an add.
  }
  window.scrollTo(0, 0)
  // This has to happen after the save button is hit.
};

// Watch changes on goalname
watch(goalname, (newGoalName) => {
  store.state.learninggoal[0].name = newGoalName;
});

// Watch changes on goaldescription
watch(goaldescription, (newGoalDescription) => {
  store.state.learninggoal[0].description = newGoalDescription;
});

// Trigger the checking route function
onBeforeRouteUpdate((to, from, next) => {
  checkRoute(to);
  next();
});
</script>