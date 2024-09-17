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

 <!-- Setup props for connection lines -->
 <script setup>
 import { BaseEdge, EdgeLabelRenderer, getBezierPath } from '@vue-flow/core'
 import { computed } from 'vue'

 const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
   id: {
     type: String,
     required: true,
   },
   sourceX: {
     type: Number,
     required: true,
   },
   sourceY: {
     type: Number,
     required: true,
   },
   targetX: {
     type: Number,
     required: true,
   },
   targetY: {
     type: Number,
     required: true,
   },
   sourcePosition: {
     type: String,
     required: true,
   },
   targetPosition: {
     type: String,
     required: true,
   },
   markerEnd: {
     type: String,
     required: true,
   },
   style: {
     type: Object,
     required: false,
   },
 })
 const path = computed(() => getBezierPath(props))
 </script>

 <script>
 export default {
   inheritAttrs: false,
 }
 </script>

<template>
  <!-- You can use the `BaseEdge` component to create your own custom edge more easily -->
  <BaseEdge
    :id="id"
    :style="style"
    :path="path[0]"
    :marker-end="markerEnd"
  />

  <!-- Use the `EdgeLabelRenderer` to escape the SVG world of edges and render your own custom label in a `<div>` ctx -->
  <EdgeLabelRenderer>
    <div
      :style="{
        pointerEvents: 'all',
        position: 'absolute',
        transform: `translate(-50%, -50%) translate(${path[1]}px,${path[2]}px)`,
        borderRadius: '50%',
        padding: '5px 10px',
        backgroundColor: '#007BFF',// Bootstrap primary color
        color: '#fff',// Text color
      }"
      class="nodrag nopan"
    >
      {{ data.text }}
    </div>
  </EdgeLabelRenderer>
</template>
