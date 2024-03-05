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

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const darkerColor = ref('#1047033')

onMounted(() => {
  darkerColor.value = darkenColor(props.data.color)
})

const darkenColor = (color) => {
  // Assuming color is in hex format, convert it to RGB
  let rgb = hexToRgb(color);
  // Darken the RGB color by reducing each component by 20%
  rgb.r *= 0.6;
  rgb.g *= 0.6;
  rgb.b *= 0.6;
  
  // Convert the darkened RGB color back to hex
  return rgbToHex(rgb.r, rgb.g, rgb.b);
}

const hexToRgb = (hex) => {
  let bigint = parseInt(hex.slice(1), 16);
  let r = (bigint >> 16) & 255;
  let g = (bigint >> 8) & 255;
  let b = bigint & 255;
  return { r, g, b };
}

const rgbToHex = (r, g, b) => {
  let colorString = '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  const index = colorString.indexOf('.'); // Get the index of the comma character
  colorString = colorString.substring(0, index); 
  return colorString;
}
 
</script>
<template>
  <div>
    <div class="module-name">
      <span class="bold">{{ data.name }}</span>
    </div>
    <div 
      class="custom-node text-center rounded p-3"
      :style="{ 
        'background-color': data.color, 
        'opacity' : data.opacity,
        'height': data.height, 
        'width': data.width ? data.width : '400px',
        'border': '5px solid ' + darkerColor
      }"
    />
  </div>
</template>

<style scoped>
.module-name {
  position: absolute;
  top: 95%;
  transform: rotate(-90deg) translate(50%, -200%);
}
.bold {
  font-weight: bold;
  font-size: 30px
}
</style>
