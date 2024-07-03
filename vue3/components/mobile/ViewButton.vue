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
  <div class="sticky-tabs">
    <button
      :class="{ active: selectedTab === false }"
      @click="selectTab(false)"
    >
      Ablaufplan
    </button>
    <button
      :class="{ active: selectedTab === true }"
      @click="selectTab(true)"
    >
      Liste
    </button>
  </div>
</template>

<script setup>


const props = defineProps({
  selectedTab: {
    type: Boolean,
    required: true,
  }
});

// Emit to parent component
const emit = defineEmits([
  'changed-view',
]);

// Function to select a tab
const selectTab = (tab) => {
  emit('changed-view', tab);
};
</script>

<style scoped>
.sticky-tabs {
  position: sticky;
  top: 0;
  background-color: white;
  display: flex;
  justify-content: center;
  padding: 0.5rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  z-index: 1000;
}

.sticky-tabs button {
  background: none;
  border: none;
  padding: 0.5rem 1rem;
  cursor: pointer;
  transition: background-color 0.3s ease;
}

.sticky-tabs button.active {
  background-color: #007bff;
  color: white;
  border-radius: 5px;
}

.sticky-tabs button:not(.active):hover {
  background-color: #f0f0f0;
}
</style>
