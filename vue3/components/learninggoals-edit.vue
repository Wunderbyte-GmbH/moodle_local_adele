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

.fade-in {
  animation: fadeIn 2s cubic-bezier(0.075, 0.82, 0.165, 1);
}

@keyframes fadeIn {
  0% { opacity: 0; }
  100% { opacity: 1; }
}

</style>
<template>
    <div>
      <notifications width="100%" />
        <div v-if="$store.state.editingadding == false &&
          $store.state.editingpretest == false &&
          $store.state.editingrestriction == false" class="fade-in">
            <LearningPathList />
        </div>
        <div v-else-if="$store.state.editingadding == true" class="fade-in">
          <div class="card p-4">
            <h2 class="mt-3">{{ store.state.strings.learninggoal_form_title_edit }}</h2>
            <div class="card-body">
              <div>
                <div v-for="goal in store.state.learninggoal">
                  <h4 class="font-weight-bold">{{ store.state.strings.fromlearningtitel }}</h4>
                  <div>
                    <input
                      v-if="$store.state.learningGoalID == 0"
                      class="form-control fancy-input"
                      :placeholder="store.state.strings.goalnameplaceholder"
                      autofocus
                      type="text"
                      v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
                      v-model="goalname"
                    />
                    <input
                      v-else
                      class="form-control fancy-input"
                      type="text"
                      v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
                      v-model="goal.name"
                    />
                  </div>
                  <div class="mb-4">
                    <h4 class="font-weight-bold">{{ store.state.strings.fromlearningdescription }}</h4>
                    <div>
                      <textarea
                        v-if="$store.state.learningGoalID == 0"
                        class="form-control fancy-input"
                        :placeholder="store.state.strings.goalsubjectplaceholder"
                        v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
                        v-model="goaldescription"
                      ></textarea>
                      <textarea
                        v-else
                        class="form-control fancy-input"
                        v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
                        v-model="goal.description"
                      ></textarea>
                    </div>
                  </div>
                  <LearingPath />
                </div>
              </div>
            </div>
          </div>

        </div>
        <div v-else-if="$store.state.editingpretest == true" class="fade-in">
          <Completion />
        </div>
        <div v-else-if="$store.state.editingrestriction == true" class="fade-in">
          <Restriction />
        </div>
    </div>
</template>

<script setup>
// Import needed libraries
import { ref, onMounted, watch, nextTick } from 'vue'
import { onBeforeRouteUpdate } from 'vue-router';
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import Completion from './completion/Completion.vue'
import Restriction from './restriction/Restriction.vue'
import LearingPath from './flowchart/LearningPath.vue'
import LearningPathList from './LearningPathList.vue'

const beforeEnter = (el) => {
  el.style.opacity = 0;
};

const afterLeave = (el) => {
  el.style.opacity = 1;
};

// Load Store and Router
const store = useStore()
const router = useRouter()

// Define constants that will be referenced
const goalname = ref('')
const goaldescription = ref('')


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
    store.dispatch('fetchUserPathRelations')

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