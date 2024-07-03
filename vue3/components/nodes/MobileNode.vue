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

<script setup>
// Import needed libraries
import { Handle, Position } from '@vue-flow/core'
import { computed, onMounted, ref } from 'vue';

const fullname = ref('')

const props = defineProps({
  data: {
    type: Object,
    required: true,
  }
});

onMounted(() => {
  fullname.value = 'Collection'
  if (props.data.fullname) {
    fullname.value = props.data.fullname
  }
})

const priorityClass = computed(() => {
  return 'priority-low';
});

</script>

<template>
  <div
    class="node-card"
    :style="[{ width: '400px' }]"
  >
    <div class="card-content">
      <span class="task-text">{{ fullname }}</span>
      <span class="icons">
        <i class="fas fa-circle" :class="priorityClass" />
      </span>
    </div>
    <Handle
      id="target"
      type="target"
      :position="Position.Top"
      class="handle"
    />
    <Handle
      id="source"
      type="source"
      :position="Position.Bottom"
      class="handle"
    />
  </div>
</template>

<style scoped>
.node-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: white;
  border: 1px solid #ccc;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  padding: 1rem;
  position: relative;
  margin-bottom: 1rem;
}

.card-content {
  display: flex;
  align-items: center;
  width: 100%;
}

.card-content input[type="checkbox"] {
  margin-right: 0.5rem;
}

.card-content .task-text {
  flex-grow: 1;
  font-size: 1.5rem;
  font-weight: bold;
  text-align: center;
}

.card-content .icons {
  display: flex;
  align-items: center;
}

.card-content .icons i {
  margin-left: 0.5rem;
}

.handle {
  width: 10px;
  height: 10px;
  background: #555;
  border-radius: 50%;
}

.priority-high {
  color: red;
}

.priority-low {
  color: green;
}
</style>