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
    <h3>{{store.state.strings.pluginname}}</h3>
    <div >
        <router-link :to="{ name: 'learninggoal-new' }" tag="button" class="btn btn-primary">{{store.state.strings.learninggoal_form_title_add}}</router-link>
    </div>
    <h2>{{store.state.strings.overviewlearningpaths}}</h2>

    <div >{{store.state.strings.learninggoals_edit_site_description}}</div>
    <span v-if="store.state.learningpaths == ''">
        {{store.state.strings.learninggoals_edit_site_no_learningpaths}}
    </span>
    <span v-else>
      <div v-for="singlelearninggoal in store.state.learningpaths" style="margin-bottom: 10px">
          <div v-if="singlelearninggoal.name !== 'not found'">
              <div>
                <div class="card" style="width: 18rem;">
                  <div class="card-body">
                    <h5 class="card-title">{{ singlelearninggoal.name }}</h5>
                    <p class="card-text">{{ singlelearninggoal.description }}</p>
                    <router-link :to="{ name: 'learninggoal-edit', params: { learninggoalId: singlelearninggoal.id }}" :title="store.state.strings.edit">
                      <i class="icon fa fa-pencil fa-fw iconsmall m-r-0" :title="store.state.strings.edit"></i>
                    </router-link>
                    <a href="" v-on:click.prevent="duplicateLearningpath(singlelearninggoal.id)" :title="store.state.strings.duplicate">
                        <i class="icon fa fa-copy fa-fw iconsmall m-r-0" :title="store.state.strings.duplicate"></i>
                    </a>
                    <a href="" v-on:click.prevent="showDeleteConfirm(singlelearninggoal.id)" :title="store.state.strings.delete">
                        <i class="icon fa fa-trash fa-fw iconsmall" :title="store.state.strings.delete"></i>
                    </a>
                    </div>
                </div>
              </div>
              <div class="alert-danger p-3 m-t-1 m-b-1" v-show="clicked[singlelearninggoal.id]">
                  <div>{{store.state.strings.deletepromptpre}}{{singlelearninggoal.name}}{{store.state.strings.deletepromptpost}}</div>
                  <div class="m-t-1">
                      <button class="btn btn-danger m-r-0" @click="deleteLearningpathConfirm(singlelearninggoal.id)" :title="store.state.strings.btnconfirmdelete">
                      {{ store.state.strings.btnconfirmdelete }}</button>
                      <button type=button @click="cancelDeleteConfirm(singlelearninggoal.id)" class="btn btn-secondary">{{store.state.strings.cancel}}</button>
                  </div>
              </div>
          </div>
        </div>
    </span>
</template>

<script setup>
// Import needed libraries
import { ref } from 'vue'
import { useStore } from 'vuex'
import { notify } from "@kyvg/vue3-notification"

// Load Store and Router
const store = useStore()

// Define constants that will be referenced
const clicked = ref({})

// Delete confirmation before learning path will be deleted
const showDeleteConfirm = (index) => {
  clicked.value = {};
  clicked.value[index] = true;
};

// Cancel learning path deletion
const cancelDeleteConfirm = (index) => {
  if (clicked.value.hasOwnProperty(index)) clicked.value[index] = !clicked.value[index];
};

// Deleting learning path
const deleteLearningpathConfirm = (learninggoalid) => {
  const result = {
    learninggoalid: learninggoalid,
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
const duplicateLearningpath = (learninggoalid) => {
  const result = {
    learninggoalid: learninggoalid,
  };
  store.dispatch('duplicateLearningpath', result);
  notify({
    title: store.state.strings.title_duplicate,
    text: store.state.strings.description_duplicate,
    type: 'success'
  });
};

</script>