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
import CompletionItem from '../completion/CompletionItem.vue'

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const toggleVisibility = () => {
  props.data.visibility = !props.data.visibility;
};

</script>

<template>
  <div class="custom-node text-center rounded p-3" style="height: 150px; width: 350px;">
    {{ data.name }}
    <button @click="toggleVisibility" 
      style="position: absolute; top: 5px; right: 5px; background: none; border: none;">
      <i class="fas" 
        :class="{ 'fa-eye': props.data.visibility, 
          'fa-eye-slash': !props.data.visibility, 
          'strikethrough': !props.data.visibility }">
      </i>
    </button>
    <CompletionItem :completion="data" />
  </div>
  <Handle id="target_and" type="target" :position="Position.Top"/>
  <Handle id="source_and" type="source" :position="Position.Bottom"/>
  <Handle id="target_or" type="target" :position="Position.Left"/>
  <Handle id="source_or" type="source" :position="Position.Right"/>
</template>

<style scoped>
.custom-node {
  background-color: white;
  padding: 10px;
  border: 1px solid #ccc;
}

.fa-eye {
  color: grey;
  opacity: 1;
}

.fa-eye-slash {
  color: grey;
  opacity: 0.5;
  text-decoration: line-through;
}

.strikethrough {
  text-decoration: line-through;
}

</style>