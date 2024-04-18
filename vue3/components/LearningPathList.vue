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
    <HelpingSlider />
    <h3>{{ store.state.strings.pluginname }}</h3>
    <div class="d-flex justify-content-between">
      <button 
        type="button" 
        class="btn btn-primary mt-4 mb-4"
        @click.prevent="addNewLearningpath()" 
      >
        {{ store.state.strings.learningpath_form_title_add }}
      </button>
      <button 
        type="button" 
        class="btn btn-secondary  mt-4 mb-4"
        data-toggle="modal" 
        data-target="#helpingSlider"
      >
        {{ store.state.strings.main_intro_slider }}
        <i class="fa-solid fa-book-open-reader" />
      </button>
    </div>
    <h2>
      {{ store.state.strings.overviewlearningpaths }}
    </h2>

    <span v-if="store.state.learningpaths == ''">
      {{ store.state.strings.learningpaths_edit_site_no_learningpaths }}
    </span>
    <span v-else>
      <div 
        v-for="singlelearningpath in store.state.learningpaths" 
        :key="singlelearningpath.id" 
        style="margin-bottom: 10px"
      >
        <div v-if="singlelearningpath.name !== 'not found'">
          <div 
            class="card shadow mt-4"
            style="max-width: 20%; min-width: 450px;"
          >
            <div class="card-header text-center">
              <h5>
                {{ singlelearningpath.name }}
              </h5>
            </div>
            <div 
              class="card-body"
              :style="[nodeBackgroundColor]"
            >
              <div 
                class="mb-2" 
                :style="{ 
                  height: '10rem',
                  backgroundImage: 'none',
                  backgroundSize: 'cover',
                  backgroundPosition: 'center',
                  backgroundColor: '#cccccc',
                  borderRadius: '1rem'
                }"
              >
                <div class="overlay">
                  <a
                    class="icon-link"
                    href="" 
                    data-toggle="tooltip" 
                    data-placement="right" 
                    :title="store.state.strings.duplicate"
                    @click.prevent="duplicateLearningpath(singlelearningpath.id)" 
                  >
                    <i 
                      class="icon fa fa-copy fa-fw iconsmall m-r-0"
                    />
                  </a>
                  <a 
                    class="icon-link"
                    href="" 
                    data-toggle="tooltip" 
                    data-placement="right"
                    :title="store.state.strings.edit"
                    @click.prevent="editLearningpath(singlelearningpath.id)" 
                  >
                    <i 
                      class="icon fa fa-pencil fa-fw iconsmall m-r-0" 
                    />
                  </a>
                  <a
                    class="icon-link"
                    href="" 
                    data-toggle="tooltip" 
                    data-placement="right"
                    :title="store.state.strings.delete"
                    @click.prevent="showDeleteConfirm(singlelearningpath.id)" 
                  >
                    <i 
                      class="icon fa fa-trash fa-fw iconsmall" 
                    />
                  </a>
                </div>
              </div>
              <div>
                <b>
                  {{ store.state.strings.main_description }}
                </b>
                {{ singlelearningpath.description }}
              </div>
            </div>
          </div>
          <div 
            v-show="clicked[singlelearningpath.id]"
            class="alert-danger p-3 m-t-1 m-b-1 rounded"
            style="max-width: 20%;"
          >
            <div>{{ store.state.strings.deletepromptpre }}{{ singlelearningpath.name }}{{ store.state.strings.deletepromptpost }}</div>

            <div class="mt-4 d-flex justify-content-between">
              <button 
                class="btn btn-danger mr-2" 
                :title="store.state.strings.btnconfirmdelete"
                @click="deleteLearningpathConfirm(singlelearningpath.id)" 
              >
                {{ store.state.strings.btnconfirmdelete }}</button>
              <button 
                type="button" 
                class="btn btn-secondary"
                @click="cancelDeleteConfirm(singlelearningpath.id)" 
              >
                {{ store.state.strings.cancel }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </span>
  </div>
</template>

<script setup>
// Import needed libraries
import { ref } from 'vue'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router';
import { notify } from "@kyvg/vue3-notification"
import HelpingSlider from '../components/modals/HelpingSlider.vue'

// Load Store and Router
const store = useStore()
const router = useRouter()

// Define constants that will be referenced
const clicked = ref({})

// Delete confirmation before learning path will be deleted
const showDeleteConfirm = (index) => {
  clicked.value = {};
  clicked.value[index] = true;
};

// Edit learning path deletion
const editLearningpath = async (singlelearningpathid) => {
  const tooltips = document.querySelectorAll('.tooltip');
  tooltips.forEach(tooltip => {
      tooltip.style.display = 'none';
  });
  store.state.learningPathID = singlelearningpathid
  await store.dispatch('fetchLearningpath')
  router.push({
    name: 'learningpath-edit',
    params: { learningpathId: singlelearningpathid  }
  })
};

// Edit learning path deletion
const addNewLearningpath = async () => {
  store.state.learningPathID = 0
  await store.dispatch('fetchLearningpath')
  router.push({
    name: 'learningpath-new',
    params: { learningpathId: 0  }
  })
};

// Cancel learning path deletion
const cancelDeleteConfirm = (index) => {
  clicked.value[index] = false
};

// Deleting learning path
const deleteLearningpathConfirm = (learningpathid) => {
  const result = {
    learningpathid: learningpathid,
  };
  store.dispatch('deleteLearningpath', result);
  clicked.value = {};
  notify({
    title: store.state.strings.title_delete,
    text: store.state.strings.description_delete,
    type: 'warn'
  });
};

// Duplicate learning path
const duplicateLearningpath = (learningpathid) => {
  const result = {
    learningpathid: learningpathid,
  };
  store.dispatch('duplicateLearningpath', result);
  notify({
    title: store.state.strings.title_duplicate,
    text: store.state.strings.description_duplicate,
    type: 'success'
  });
};

</script>

<style scoped>
  .overlay {
    position: relative;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: rgba(0, 0, 0, 0.4); /* Semi-transparent gray */
    display: flex;
    justify-content: center;
    align-items: center;
    width: 70%; /* Adjust width as needed */
    height: 50%; /* Adjust height as needed */
    border-radius: 15px; /* Rounded edges */
  }

  .icon-link {
    color: white;
    cursor: pointer;
    padding: 10px;
    margin: 0 15px;
    display: inline-flex;
  }

  .fa-copy,
  .fa-pencil,
  .fa-trash {
    font-size: 20px;
  }

  .icon-link:hover {
    color: lightgray; /* Hover effect */
  }

</style>
