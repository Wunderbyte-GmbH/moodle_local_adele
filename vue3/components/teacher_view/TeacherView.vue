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
    <div>
      <div v-if="learningpath">
        <TextInputs 
          :goal="learningpath"
        />
        <LearningPath :learningpath="learningpath" />
        <UserList :learning-path-id="learningpath" />
      </div>
    </div>
  </div>
</template>
  
  <script setup>
  // Import needed libraries
import { onMounted, ref } from 'vue';
import { useStore } from 'vuex';
import LearningPath from '../flowchart/LearningPath.vue';
import TextInputs from '../charthelper/textInputs.vue';
import UserList from '../user_view/UserList.vue';

const store = useStore()
const learningpath = ref(null)

onMounted( async () => {
  learningpath.value = await store.dispatch('fetchLearningpath')
  store.dispatch('fetchUserPathRelations')
})
</script>