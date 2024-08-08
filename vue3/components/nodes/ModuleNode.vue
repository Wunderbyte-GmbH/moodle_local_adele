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
import { Handle, Position } from '@vue-flow/core'
import { computed, onMounted, ref } from 'vue';
import darkenColor from '../../composables/nodesHelper/darkenColor'

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  zoomstep: {
    type: Number,
    required: true,
  },
});

const darkerColor = ref('#1047033')
const backgroundColor = ref('#1047033')

onMounted(() => {
  darkerColor.value = darkenColor(props.data.color_inactive ?? props.data.color)
  backgroundColor.value = addOpacity(props.data)
})

const addOpacity = (data) => {
  let color = data.color_inactive ?? data.color
  color = color.replace('#', '');

  // Parse the r, g, b values
  let r = parseInt(color.substring(0, 2), 16);
  let g = parseInt(color.substring(2, 4), 16);
  let b = parseInt(color.substring(4, 6), 16);

  // Return the RGBA color
  return `rgba(${r}, ${g}, ${b}, ${data.opacity})`;
}

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))


</script>
<template>
  <div>
    <div
      class="custom-node text-center p-3"
      :style="{
        'background-color': backgroundColor,
        'height': data.height,
        'width': data.width ? data.width : '400px'
      }"
    />
    <div
      class="module-name"
      :style="{
        'background-color': backgroundColor,
        'border': '5px solid ' + darkerColor,
        'border-bottom': '5px solid ' + darkerColor,
        'border-radius': '8px 8px 0 0'
      }"
    >
      <span
        :class="zoomstep == '0.2' ? 'bold-outer' : 'bold'"
      >
        {{ data.name }}
      </span>
    </div>
    <Handle
      v-if="zoomstep == '0.2'"
      id="target"
      type="target"
      :position="Position.Top"
      :style="handleStyle"
    />
    <Handle
      v-if="zoomstep == '0.2'"
      id="source"
      type="source"
      :position="Position.Bottom"
      :style="handleStyle"
    />
  </div>
</template>

<style scoped>

  .custom-node {
    position: relative;
    border-radius: 8px;
    border-bottom-left-radius: 0;
  }

  .module-name {
    position: absolute;
    bottom: 0;
    left: 0;
    transform: rotate(-90deg);
    transform-origin: bottom left;
    padding: 2px 5px;
    border-radius: 8px 8px 0 0;
  }

  .bold {
    font-weight: bold;
    font-size: 30px;
    padding: 10px;
    color: black;
  }
  .bold-outer {
    font-weight: bold;
    font-size: clamp(34px, 3.5vw, 64px);
    padding: 10px;
    color: black;
  }
</style>
