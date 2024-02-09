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
    <div>
      <router-link 
        :to="{ name: 'learningpath-new' }" 
        tag="button" 
        class="btn btn-primary mr-2"
      >
        {{ store.state.strings.learningpath_form_title_add }}
      </router-link>
      <button 
        type="button" 
        class="btn btn-secondary"
        data-toggle="modal" 
        data-target="#helpingSlider"
      >
        Show helping slider
      </button>
    </div>
    <h2>{{ store.state.strings.overviewlearningpaths }}</h2>

    <div>{{ store.state.strings.learningpaths_edit_site_description }}</div>
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
          <div>
            <div 
              class="card" 
              style="width: 18rem;"
            >
              <div class="card-body">
                <h5 class="card-title">{{ singlelearningpath.name }}</h5>
                <p class="card-text">{{ singlelearningpath.description }}</p>
                <a 
                  :title="store.state.strings.edit"
                  href="" 
                  @click.prevent="editLearningpath(singlelearningpath.id)" 
                >
                  <i 
                    class="icon fa fa-pencil fa-fw iconsmall m-r-0" 
                    :title="store.state.strings.edit" 
                  />
                </a>
                <a 
                  :title="store.state.strings.duplicate"
                  href="" 
                  @click.prevent="duplicateLearningpath(singlelearningpath.id)" 
                >
                  <i 
                    class="icon fa fa-copy fa-fw iconsmall m-r-0" 
                    :title="store.state.strings.duplicate" 
                  />
                </a>
                <a 
                  :title="store.state.strings.delete"
                  href="" 
                  @click.prevent="showDeleteConfirm(singlelearningpath.id)" 
                >
                  <i 
                    class="icon fa fa-trash fa-fw iconsmall" 
                    :title="store.state.strings.delete"
                  />
                </a>
              </div>
            </div>
          </div>
          <div 
            v-show="clicked[singlelearningpath.id]"
            class="alert-danger p-3 m-t-1 m-b-1" 
          >
            <div>{{ store.state.strings.deletepromptpre }}{{ singlelearningpath.name }}{{ store.state.strings.deletepromptpost }}</div>
            <div class="m-t-1">
              <button 
                class="btn btn-danger m-r-0" 
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
const editLearningpath = (singlelearningpathid) => {
  store.state.learningPathID = singlelearningpathid
  router.push({
    name: 'learningpath-edit',
    params: { learningpathId: singlelearningpathid  }
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

const showHelpingSlides = () => {
  console.log('hello')
}

</script>