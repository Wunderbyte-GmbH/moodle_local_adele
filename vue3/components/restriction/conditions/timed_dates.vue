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
 * timed dates component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="form-check">
    <div class="input-group mb-3 d-flex flex-column align-items-center">
      <span class="input-group-text rounded-end-0">
        {{ descriptions.start }}
        <TimeWarning />
      </span>
      <input
        :id="`restriction-${restriction.node_id}-start`"
        type="datetime-local"
        :name="`restriction-${restriction.node_id}-start`"
        class="form-control"
        style="
          width: 80%;
          border-radius: 0.5rem !important;
        "
        :value="toDatetimeLocal(data.start)"
        @input="updateSelectedDateTime('start', $event)"
      >
    </div>
    <div class="input-group mb-3 d-flex flex-column align-items-center">
      <span class="input-group-text rounded-end-0">
        {{ descriptions.end }}
        <TimeWarning />
      </span>
      <input
        :id="`restriction-${restriction.node_id}-end`"
        type="datetime-local"
        :name="`restriction-${restriction.node_id}-end`"
        class="form-control"
        style="
          width: 80%;
          border-radius: 0.5rem !important;
        "
        :value="toDatetimeLocal(data.end)"
        @input="updateSelectedDateTime('end', $event)"
      >
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import TimeWarning from '../../nodes_items/TimeWarning.vue'
import { toDatetimeLocal, toEpochSeconds } from '../../../composables/timeValue.js'

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  }, 
  restriction: {
    type: Object,
    required: true,
  },
  });
const data = ref({
  start: null,
  end: null,
});
const descriptions = ref({
  start: null,
  end: null,
});
const emit = defineEmits(['update:modelValue'])

// Stored as Unix seconds (#581): the input speaks the browser's local time,
// the stored value is one instant regardless of who looks at it.
const updateSelectedDateTime = (type, event) => {
  data.value[type] = toEpochSeconds(event.target.value);
  emit('update:modelValue', data.value);
};

// Initialize the input with the modelValue
onMounted(() => {
  if (props.modelValue != null) {
    data.value = props.modelValue;
  } 
  let tmp_descriptions = props.restriction.description.split(';')
  descriptions.value.start = tmp_descriptions[0]
  descriptions.value.end = tmp_descriptions[1]
});

// Watch for changes in modelValue
watch(() => props.modelValue, (newValue) => {
  data.value = newValue;
}, { deep: true } );
</script>