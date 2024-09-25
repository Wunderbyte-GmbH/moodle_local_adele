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
  <div v-if="userLearningpath">
    <div v-if="isMobile">
      <MobileViews
        :selected-tab="selectedTab"
      />
      <ViewButton
        :selected-tab="selectedTab"
        @changed-view="toggleView"
      />
    </div>
    <div v-else>
      <UserPath
        :userlearningpathparent="userLearningpath"
      />
      <UserList />
    </div>
  </div>
</template>

<script setup>
// Import needed libraries
import { onMounted, onUnmounted, ref } from 'vue'
import UserList from '../user_view/UserList.vue'
import UserPath from '../user_view/UserPath.vue'
import ViewButton from '../mobile/ViewButton.vue'
import MobileViews from '../mobile/MobileViews.vue'
import { useStore } from 'vuex'
import { useRoute } from 'vue-router'

const store = useStore()
const route = useRoute()
const learningpath = ref(null)
const userLearningpaths = ref(null)
const selectedTab = ref(true)
const userLearningpath = ref(null)


const isMobile = ref(window.innerWidth <= 600)

const updateDeviceType = () => {
  isMobile.value = window.innerWidth <= 600
};

const toggleView = (view) => {
  selectedTab.value = view
};

onMounted(async() => {
  // Check if available courses are set
  if (!store.state.availablecourses) {
    store.dispatch('fetchAvailablecourses')
  }
  let params = []
  if (store.state.view == 'student') {
    params = {
      learningpathId: store.state.learningPathID,
      userId: store.state.user,
    }
  }else {
    params = route.params
  }
  userLearningpath.value = await store.dispatch('fetchUserPathRelation', params)
  learningpath.value = await store.dispatch('fetchLearningpath')
  userLearningpaths.value = await store.dispatch('fetchUserPathRelations')
  window.addEventListener('resize', updateDeviceType)
  updateDeviceType()
})

// Remove event listener on unmount
onUnmounted(() => {
  window.removeEventListener('resize', updateDeviceType)
});


</script>