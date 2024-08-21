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
    <notifications width="100%" />
    <div v-if="store.state.view=='teacher'">
      <TeacherView />
    </div>
    <div v-else-if="store.state.view=='student'">
      <StudentView />
    </div>
    <div v-else>
      <div
        v-if="$store.state.viewing == true"
        class="fade-in"
      >
        <button
          :to="{ name: 'learningpaths-edit-overview' }"
          tag="button"
          class="btn btn-outline-primary mb-2"
          :disabled="showBackConfirmation"
          @click="goBack"
        >
          <i class="fa fa-arrow-left" /> Go Back to Overview
        </button>
        <div
          class="card p-4"
          style="padding: 2.5rem !important;"
        >
          <div class="card-body">
            <div v-if="store.state.learningpath">
              <h2>
                {{store.state.learningpath.name}}
              </h2>
              {{store.state.learningpath.description}}
              <div v-if="learningpath">
                <LearningPathView
                  :learningpath="learningpath"
                  @finish-edit="finishEdit"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
      <div
        v-else-if="store.state.editingadding == false &&
          store.state.editingpretest == false &&
          store.state.editingrestriction == false &&
          store.state.viewing == false"
        class="fade-in"
      >
        <LearningPathList />
      </div>
      <div
        v-else-if="store.state.editingadding == true"
        class="fade-in"
      >
        <button
          :to="{ name: 'learningpaths-edit-overview' }"
          tag="button"
          class="btn btn-outline-primary mb-2"
          :disabled="showBackConfirmation"
          @click="goBackConfirmation(false)"
        >
          <i class="fa fa-arrow-left" /> Go Back to Overview
        </button>
        <div
          v-if="showBackConfirmation"
          class="cancelConfi"
        >
          {{ store.state.strings.flowchart_cancel_confirmation }}
          <button
            id="cancel-learning-path"
            class="btn btn-primary m-2"
            @click="goBack"
          >
            {{ store.state.strings.flowchart_back_button }}
          </button>
          <button
            id="confim-cancel-learning-path"
            class="btn btn-warning m-2"
            @click="goBackConfirmation(true)"
          >
            {{ store.state.strings.flowchart_cancel_button }}
          </button>
        </div>
        <div
          class="card p-4"
          style="padding: 2.5rem !important;"
        >
          <h2 class="mt-3">
            {{ store.state.strings.learningpath_form_title_edit }}
          </h2>
          <div class="card-body">
            <div v-if="store.state.learningpath">
              <TextInputs
                :goal="store.state.learningpath"
                @change-GoalName="changeGoalName"
                @change-GoalDescription="changeGoalDescription"
                @change-LpImage="changeLpImage"
              />
              <div v-if="learningpath">
                <LearingPath
                  :learningpath="learningpath"
                  @finish-edit="finishEdit"
                  @removeNodeConditions="handleRemoveNode"
                  @addEdge="handleAddEdge"
                  @removeEdge="handleRemoveEdge"
                  @saveEdit="handleSaveEdit"
                  @moveNode="handleMoveNode"
                  @changedModule="onChangedModule"
                  @changedLearningpathTree="onChangedTree"
                  @saveEditCourse="handleSaveEditCourse"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
      <div
        v-else-if="$store.state.editingpretest == true"
        class="fade-in"
      >
        <Completion :learningpath="learningpath" />
      </div>
      <div
        v-else-if="$store.state.editingrestriction == true"
        class="fade-in"
      >
        <Restriction :learningpath="learningpath" />
      </div>
    </div>
  </div>
</template>

<script setup>
// Import needed libraries
import { ref, onMounted, nextTick } from 'vue'
import { onBeforeRouteUpdate, useRoute } from 'vue-router';
import { useStore } from 'vuex'
import { useRouter } from 'vue-router'
import Completion from './completion/CompletionFlow.vue'
import Restriction from './restriction/RestrictionFlow.vue'
import LearingPath from './flowchart/LearningPath.vue'
import LearningPathView from './flowchart/LearningPathView.vue';
import LearningPathList from './LearningPathList.vue'
import TextInputs from './charthelper/textInputs.vue'
import TeacherView from './teacher_view/TeacherView.vue';
import StudentView from './student_view/StudentView.vue';
import { notify } from "@kyvg/vue3-notification";
import removeNodeConditions from '../composables/flowHelper/removeNodeConditions';

const store = useStore()
// Load Store and Router
const router = useRouter()
const route = useRoute()

// Define constants that will be referenced
const goalname = ref('')
const goaldescription = ref('')
const learningpath = ref('')
const copiedLearningpath = ref({})
const showBackConfirmation = ref(false)


const handleRemoveNode = (data) => {
  let nodes = []
  learningpath.value.json.tree.nodes.forEach((node) => {
    if (node.id != data) {
      node = removeNodeConditions(node, data)
      nodes.push(node)
    }
  })
  learningpath.value.json.tree.nodes = nodes
}

const changeGoalName = (newGoalName) => {
  store.state.learningpath.name = newGoalName;
}

const changeGoalDescription = (newGoalDescription) => {
  store.state.learningpath.description = newGoalDescription;
}

const changeLpImage = (newLpImage) => {
  store.state.learningpath.image = newLpImage;
}

// Checking routes
const checkRoute = (currentRoute) => {
  if(currentRoute == undefined && !route.path.includes('/learningpaths/edit')){
        router.push({ name: 'learningpaths-edit-overview' });
  } else if (currentRoute == undefined && route.path.includes('/learningpaths/edit') && route.params.learningpathId) {
      store.state.editingadding = true;
      nextTick(() => showForm(route.params.learningpathId));
  } else if (currentRoute == undefined){
        router.push({ name: 'learningpaths-edit-overview' });
    }
  else if (currentRoute.name === 'learningpath-edit' ) {
    store.state.editingadding = true;
    nextTick(() => showForm(currentRoute.params.learningpathId));
  } else if (currentRoute.name === 'learningpath-new') {
    store.state.editingadding = true;
    nextTick(() => showForm(null));
  } else if (currentRoute.name === 'learningpath-view') {
    store.state.viewing = true;
    nextTick(() => showForm(null));
  }
};

// Trigger web services on mount
onMounted(() => {
  if (route.query.notify) {
    notify({
      title: store.state.strings.title_save,
      text: store.state.strings.description_save,
      type: 'success',
    });
    router.replace({
      path: route.path,
      query: {}
    });
  }

  store.dispatch('fetchAvailablecourses');
  if(store.state.view!='student'){
    store.dispatch('fetchLearningpaths');
  }

  router.isReady().then(() => {
    console.log('route', route.fullPath, route.params);
    checkRoute(router.value);
  }).catch((err) => console.log(err));
});

// Showing form to generate or edit learning path
const showForm = async (learningpathId = null) => {
  goalname.value = ''
  goaldescription.value = ''
  if (learningpathId) {
    store.state.learningPathID = learningpathId;
  }
  learningpath.value = await store.dispatch('fetchLearningpath')
  copiedLearningpath.value = JSON.parse(JSON.stringify(learningpath.value))
  store.state.editingadding = true
  window.scrollTo(0, 0)
};

// Trigger the checking route function
onBeforeRouteUpdate((to, from, next) => {
  console.log('router to', to);
  checkRoute(to);
  next();
});


// Function to go back
const goBack = () => {
  copiedLearningpath.value
  if (
    learningpath.value.description == copiedLearningpath.value.description &&
    learningpath.value.name == copiedLearningpath.value.name
  ) {
    let same = true
    if (
      (
        copiedLearningpath.value.json.tree &&
        learningpath.value.json.tree &&
        copiedLearningpath.value.json.tree.nodes &&
        learningpath.value.json.tree.nodes &&
        copiedLearningpath.value.json.tree.nodes.length != learningpath.value.json.tree.nodes.length
      ) ||
      typeof(copiedLearningpath.value.json) != typeof(learningpath.value.json)
    ) {
      same = false
    }
    if (same && copiedLearningpath.value.json.tree) {
      learningpath.value.json.tree.nodes.forEach((node, index) => {
        if (
          !copiedLearningpath.value.json.tree.nodes[index] ||
          JSON.stringify(node.data) != JSON.stringify(copiedLearningpath.value.json.tree.nodes[index].data)
        ) {
          showBackConfirmation.value = !showBackConfirmation.value
          same = false
        }
      })
    }
    if (same) {
      goBackConfirmation(false)
    } else {
      showBackConfirmation.value = !showBackConfirmation.value
    }
  } else {
    showBackConfirmation.value = !showBackConfirmation.value
  }
}

const goBackConfirmation = (toggle) => {
  if (toggle) {
    goBack()
  }
  store.state.editingadding = false
  store.state.viewing = false
  store.state.editingrestriction = false
  store.state.editingpretest = false
  store.state.learningPathID = null
  store.state.learningpath = null;
  router.push({name: 'learningpaths-edit-overview'});
}

const finishEdit = () => {
  learningpath.value = null
}

const handleAddEdge = (edge) => {
  if (edge && learningpath.value.json.tree.edges) {
    learningpath.value.json.tree.edges.push(edge)
  }
}
const handleRemoveEdge = (edges) => {
  if (edges && learningpath.value.json) {
    learningpath.value.json.tree.edges = edges
  }
}

const onChangedModule = (learningpath) => {
  learningpath.value = learningpath
}

const onChangedTree = (tree) => {
  if (learningpath.value.json == '') {
    learningpath.value.json = {
      tree : tree
    }
  } else {
    learningpath.value.json.tree = tree
  }
}

const handleSaveEdit = async (params) => {
  learningpath.value.json.tree.nodes.forEach((node) => {
    if (node.id == params.node_id) {
      node.data.fullname = params.fullname
      node.data.selected_image = params.selected_image
      node.data.selected_course_image = params.selected_course_image
    }
  })

  notify({
    title: store.state.strings.title_save,
    text: store.state.strings.description_save,
    type: 'success'
  })
}

const handleSaveEditCourse = async (params) => {
  learningpath.value.json.tree.nodes.forEach((node) => {
    if (node.id == store.state.node.node_id) {
      node.data.fullname = params.fullname
      node.data.description = params.description
    }
  })

  notify({
    title: store.state.strings.title_save,
    text: store.state.strings.description_save,
    type: 'success'
  })
}



const handleMoveNode = (params) => {
  learningpath.value.json.tree.nodes.forEach((node) => {
    if (node.id == params.id) {
      node.position = params.position
      node.computedPosition = params.computedPosition
    }
  })
}

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