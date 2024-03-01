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
  <div class="mt-4">
    <button 
      type="button" 
      class="btn btn-secondary"
      @click="toggleAddForm" 
    >
      Add a learning module
    </button>
    <transition name="fade">
      <div 
        v-if="addLeaerningModule" 
        class="mt-2 addForm"
        :style="{ backgroundColor: addFormColor }"
      >
        <div class="row">
          <div class="col-md-12">
            <label for="title" class="form-label">Title:</label>
            <input type="text" id="title" v-model="title" class="form-control">
            <div 
              v-if="showValidation"
              class="titleValidation"
              :style="{backgroundColor: backgroundValidation, color: colorValidation}"
            >
              Please provide a name!
            </div>
          </div>
        </div>
        <div class="row mt-2">
          <div class="col-md-12">
            <label for="color" class="form-label">Color:</label>
            <input type="color" id="color" v-model="color" class="form-control">
          </div>
        </div>
        <div class="mt-2 d-flex justify-content-between">
          <button 
            type="button" 
            class="btn btn-secondary me-2"
            @click="toggleAddForm" 
          >
            Cancel
          </button>
          <button 
            type="button" 
            class="btn btn-primary"
            @click="addLearningModule" 
          >
            Add
          </button>
        </div>
      </div>
    </transition>
    <div v-if="learningmodules.length > 0">
      Existing learning modules
      <div 
        v-for="module in learningmodules" 
        :key="module.id"
        class="vue-flow__node-input mt-1 row align-items-center justify-content-between"
        :draggable="false" 
        :data="module" 
        style="width: 95%; padding-left: 1rem; margin-left: 0.025rem;"
      >    
        <div class="d-flex align-items-center justify-content-between">
          <span 
            class="color-circle" 
            :style="{ backgroundColor: module.color }"
          />
          <span class="ml-2">{{ module.name }}</span> <!-- Add margin to the left -->
        </div>
        <a 
          href="" 
          @click.prevent="editLearningModule(module)" 
        >
          <i 
            class="icon fa fa-pencil fa-fw iconsmall m-r-0" 
            :title="store.state.strings.edit" 
          />
        </a>
        <transition name="fade">
          <div 
            v-if="showEdit == module.id" 
            class="col-12 text-center mt-2"
          >
            <div class="col-md-12">
              <label for="canceltitle" class="form-label">Title:</label>
              <input type="text" id="canceltitle" v-model="cancelTitle" class="form-control">
              <div 
                v-if="showValidation"
                class="titleValidation"
                :style="{backgroundColor: backgroundValidation, color: colorValidation}"
              >
                Please provide a name!
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-12">
                <label for="color" class="form-label">Color:</label>
                <input type="color" id="color" v-model="cancelColor" class="form-control">
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-12">
                <button 
                  type="button" 
                  class="btn btn-secondary mr-2"
                  @click="cancelModule()"
                >
                  Cancel
                </button>
                <button 
                  type="button" 
                  class="btn btn-primary mr-2"
                  @click="saveModule(module.id)"
                >
                  Save
                </button>
                <button 
                  type="button" 
                  class="btn btn-danger"
                  @click="deleteModule(module.id)"
                >
                  Delete
                </button>
              </div>
            </div>
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<script setup>

import { onMounted, ref, watch } from 'vue'
import { useStore } from 'vuex';

// Defined props from the parent component
const props = defineProps({
  learningmodule: {
    type: Array,
    required: true,
  },
  strings: {
    type: Object,
    required: true,
  }
});


const learningmodules = ref([])
const learningpath = ref([])
const addLeaerningModule = ref(false)
const title = ref('')
const color = ref('#000000')
const addFormColor = props.strings.LIGHT_SEA_GREEN
const showValidation = ref(false)
const backgroundValidation = props.strings.PUMPKIN
const colorValidation = props.strings.CRIMSON
const showEdit = ref('null')
const cancelTitle = ref('')
const cancelColor = ref('')

// Load Store and Router
const store = useStore();

onMounted(() => {
  setLearningModules()
});

watch(() => props.learningmodule, async () => {
  setLearningModules()
}, { deep: true } );

const setLearningModules = () => {
  learningpath.value = props.learningmodule
  if (props.learningmodule.json.modules) {
    learningmodules.value = props.learningmodule.json.modules
  } else {
    learningmodules.value = []
  }
}

const toggleAddForm = () => {
  addLeaerningModule.value = !addLeaerningModule.value
  showValidation.value = false
  title.value = ''
  color.value = '#000000'
}

const addLearningModule = () => {
  if (title.value == '') {
    showValidation.value = true
  } else {
    let newModule = {
      id: 0,
      name: title.value,
      color: color.value,
    }
    if (learningpath.value.json.modules && learningpath.value.json.modules.length > 0) {
      let lastElement = learningpath.value.json.modules[learningpath.value.json.modules.length - 1];
      newModule.id = lastElement.id +1
      learningpath.value.json.modules.push(
        newModule
        )
    } else {
      learningpath.value.json.modules = [newModule]
    }
    learningmodules.value = learningpath.value.json.modules
    store.dispatch('saveLearningpath', learningpath.value)
    toggleAddForm()
  }
}

const editLearningModule = (module) => {
  if (showEdit.value == module.id) {
    showEdit.value = 'null'
    setEdit(0, module)
  } else {
    showEdit.value = module.id
    setEdit(1, module)
  }
}

const setEdit = (type, module) => {
  if (type) {
    cancelTitle.value = module.name
    cancelColor.value = module.color
  } else {
    cancelTitle.value = ''
    cancelColor.value = ''
    showEdit.value = 'null'
  }
}

const cancelModule = () => {
  setEdit(0)
}
const saveModule = (id) => {
  editElementById(learningpath.value.json.modules, id)
  store.dispatch('saveLearningpath', learningpath.value)
  setEdit(0)
}
const deleteModule = (id) => {
  removeElementById(learningpath.value.json.modules, id);
  store.dispatch('saveLearningpath', learningpath.value)
  setEdit(0)
}

function removeElementById(jsonData, idToRemove) {
    for (var i = 0; i < jsonData.length; i++) {
        if (jsonData[i].id === idToRemove) {
            jsonData.splice(i, 1);
            break;
        }
    }
}

function editElementById(jsonData, idToEdit) {
    for (var i = 0; i < jsonData.length; i++) {
        if (jsonData[i].id === idToEdit) {
            jsonData[i].name = cancelTitle.value
            jsonData[i].color = cancelColor.value
            break;
        }
    }
}

</script>

<style scoped>
  .fade-enter-active, .fade-leave-active {
    transition: opacity 0.5s;
  }
  .fade-enter-from, .fade-leave-to {
    opacity: 0;
  } 
  .addForm{
    padding: 0.5rem;
    border-radius: 0.5rem;
  }
  .titleValidation{
    padding: 0.5rem;
    border-radius: 0.5rem;
  }
  .color-circle {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 1px solid black; /* Adjust border width and color as needed */
  border-radius: 50%; /* Make it a circle */
  margin-right: 5px; /* Adjust spacing between circle and module name */
}

</style>