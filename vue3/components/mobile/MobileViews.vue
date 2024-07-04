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
  <div v-if="details">
    <DetailView
      :details="details"
      @changed-details="changeDetail"
    />
  </div>
  <div v-else-if="selectedTab">
    <ListView
      @changed-details="changeDetail"
    />
  </div>
  <div v-else>
    <PathView
      @changed-details="changeDetail"
    />
  </div>
</template>

<script setup>

import PathView from './views/PathView.vue';
import ListView from './views/ListView.vue';
import { ref } from 'vue';
import DetailView from './views/DetailView.vue';

const props = defineProps({
  selectedTab: {
    type: Boolean,
    required: true,
  }
});

const details = ref(null)

const changeDetail = (node) => {
  details.value = node
};

</script>
