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
    <div v-if="learningmodules.count > 0">
      We have something
      {{ learningmodules }}
    </div>
  </div>
</template>

<script setup>

import { onMounted, ref } from 'vue'

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
const addLeaerningModule = ref(false)
const title = ref('')
const color = ref('#000000')
const addFormColor = props.strings.LIGHT_SEA_GREEN

onMounted(() => {
  if (props.learningmodule.json.learningmodule) {
    learningmodules.value = props.learningmodule.json.learningmodule
  }
});

const toggleAddForm = () => {
  addLeaerningModule.value = !addLeaerningModule.value
}

const addLearningModule = () => {
  addLeaerningModule.value = !addLeaerningModule.value
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
</style>