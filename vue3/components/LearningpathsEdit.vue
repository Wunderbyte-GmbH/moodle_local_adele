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
    <div v-if="store.state.view=='teacher'">
      <TeacherView />
    </div>
    <div v-else-if="store.state.view=='student'">
      <StudentView />
    </div>
    <div v-else>
      <notifications width="100%" />
      <div 
        v-if="$store.state.editingadding == false &&
          $store.state.editingpretest == false &&
          $store.state.editingrestriction == false" 
        class="fade-in"
      >
        <LearningPathList />
      </div>
      <div 
        v-else-if="$store.state.editingadding == true" 
        class="fade-in"
      >
        <div class="card p-4" style="padding: 2.5rem !important;">
          <h2 class="mt-3">
            {{ store.state.strings.learningpath_form_title_edit }}
          </h2>
          <div class="card-body">
            <div>
              <div v-if="store.state.learningpath">
                <TextInputs 
                  :goal="store.state.learningpath" 
                  @change-GoalName="changeGoalName" 
                  @change-GoalDescription="changeGoalDescription"
                />
                <LearingPath />
              </div>
            </div>
          </div>
        </div>
      </div>
      <div 
        v-else-if="$store.state.editingpretest == true" 
        class="fade-in"
      >
        <Completion />
      </div>
      <div 
        v-else-if="$store.state.editingrestriction == true" 
        class="fade-in"
      >
        <Restriction />
      </div>
    </div>
  </div>
</template>

<script setup>
// Import needed libraries
import { ref, onMounted, nextTick } from 'vue'
import { onBeforeRouteUpdate } from 'vue-router';
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import Completion from './completion/CompletionFlow.vue'
import Restriction from './restriction/RestrictionFlow.vue'
import LearingPath from './flowchart/LearningPath.vue'
import LearningPathList from './LearningPathList.vue'
import TextInputs from './charthelper/textInputs.vue'
import TeacherView from './teacher_view/TeacherView.vue';
import StudentView from './student_view/StudentView.vue';

const store = useStore()
// Load Store and Router
const router = useRouter()

// Define constants that will be referenced
const goalname = ref('')
const goaldescription = ref('')

const changeGoalName = (newGoalName) => {
  store.state.learningpath.name = newGoalName;
}

const changeGoalDescription = (newGoalDescription) => {
  store.state.learningpath.description = newGoalDescription;
}

// Checking routes 
const checkRoute = (currentRoute) => {
    if(currentRoute == undefined){
        router.push({ name: 'learningpaths-edit-overview' });
    }
  else if (currentRoute.name === 'learningpath-edit') {
    store.state.editingadding = true;
    nextTick(() => showForm(currentRoute.params.learningpathId));
  } else if (currentRoute.name === 'learningpath-new') {
    store.state.editingadding = true;
    nextTick(() => showForm(null));
  }
};

// Trigger web services on mount
onMounted(() => {
  if(store.state.view!='student'){
    store.dispatch('fetchLearningpaths');
    store.dispatch('fetchAvailablecourses');
  }
  checkRoute(router.value);
});

// Showing form to generate or edit learning path
const showForm = async (learningpathId = null) => {
  goalname.value = ''
  goaldescription.value = ''
  if (learningpathId) {
    store.state.learningPathID = learningpathId;
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

// Trigger the checking route function
onBeforeRouteUpdate((to, from, next) => {
  checkRoute(to);
  next();
});
</script>

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