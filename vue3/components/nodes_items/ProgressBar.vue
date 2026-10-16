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
 * Progress Bar component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="progress-container">
    <div
      class="progress-bar"

      role="progressbar"
       :style="[progressStyle, { width: props.progress + '%' }]"
      :aria-valuenow="props.progress"
      aria-valuemin="0"
      aria-valuemax="100"
    />
    <div 
      class="progress-label" 
      :style="props.progress > 0 ? 'font-size: 0.8em' : 'font-size: 0.55em'"
    > 
      {{ props.progress > 0 ? props.progress + '%' : store.state.strings.nodes_items_no_progress }}
    </div> 
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useStore } from 'vuex';
import * as nodeColors from '../../config/nodeColors';

const store = useStore();

const props = defineProps({
  progress: {
    type: Number,
    required: true,
  },
  status: {
    type: String,
    required: false,
  }
});


const progressStyle = computed(() => {
  let color;
  switch (props.status) {
    case '0':
      color = nodeColors.progressBarColorCase0;
      break;
    case 'a1':
      color = nodeColors.progressBarColorCaseA1;
      break;
    case 'a2':
      color = nodeColors.progressBarColorCaseA2;
      break;
    case 'b':
      color = nodeColors.progressBarColorCaseB;
      break;
    case 'c':
      color = nodeColors.progressBarColorCaseC;
      break;
    case 'd':
      color = nodeColors.progressBarColorCaseD;
      break;
    case 'e':
      color = nodeColors.progressBarColorCaseE;
      break;
    case 'f':
      color = nodeColors.progressBarColorCaseF;
      break;
    default:
      color = nodeColors.progressBarColorCaseDefault;
      break;
  }
  return { backgroundColor: color };
});
</script>

<style scoped>
.progress-container {
  width: 100%;
  height: 28px;
  overflow: hidden;
  border-radius: 10px;
  position: relative;
  background: #999999;
}

.progress-bar {
  text-align: center;
  line-height: 28px;
  height: 100%;
  color: #fff;
  border-radius: 10px;
  transition: width 0.3s ease, background-color 0.3s ease; /* Add a smooth transition effect */
}

/* Customize the background color when progress is zero */
.progress-bar.bg-success {
  background-color: #28a745;
}

.progress-label {
  position: absolute;
  line-height: 28px;
  height: 100%;
  position: absolute;
  top: 0;
  width: 100%;
  text-align: center;
  color: #fff;
}
</style>
