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
import { onMounted, ref } from 'vue';
import darkenColor from '../../composables/nodesHelper/darkenColor';

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const darkerColor = ref('#1047033')

onMounted(() => {
  darkerColor.value = darkenColor(props.data.color_inactive ?? props.data.color)
})

</script>
<template>
  <div>
    <div class="module-name">
      <span class="bold">{{ data.name }}</span>
    </div>
    <div
      class="custom-node text-center p-3"
      :style="{
        'background-color': data.color_inactive ?? data.color,
        'opacity' : data.opacity,
        'height': data.height,
        'width': data.width ? data.width : '400px'
      }"
    >
      <div
        class="module-name"
        :style="{
          'background-color': data.color_inactive ?? data.color,
          'border': '5px solid ' + darkerColor,
          'border-bottom': '5px solid ' + darkerColor,
          'border-radius': '8px 8px 0 0'
        }"
      >
        <span class="bold">{{ data.name }}</span>
      </div>
    </div>
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
  }
</style>
