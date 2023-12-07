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

 const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});
 
 </script>
<template>
  <div class="custom-node text-center rounded p-3"
    :style="{ 'background-color': data.bgcolor, 'opacity' : data.opacity,
      'height': data.height, 'width': data.width
    }">
    {{ data.infotext}}
  </div>
  <Handle id="target_and" type="target" :position="Position.Top"/>
  <Handle id="source_and" type="source" :position="Position.Bottom"/>
  <Handle id="target_or" type="target" :position="Position.Left"/>
  <Handle id="source_or" type="source" :position="Position.Right"/>
</template>

