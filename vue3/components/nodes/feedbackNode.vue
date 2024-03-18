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
import { useStore } from 'vuex';

const store = useStore()

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const feedback = ref([])

onMounted(async () => {
  feedback.value = props.data
});

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

</script>

<template>
  <div
    :class="{ 'custom-node': true, 'has-text': props.data.feedback }"
    class="custom-node rounded p-3"
    style="width: 350px; min-height: 200px;"
  >
    <div class="text-center">
      <div class="container">
        <div class="row justify-content-center">
          <div 
            class="col-md-12"
          >
            <div class="mb-3">
              <label 
                for="exampleFormControlTextarea1" 
                class="form-label"
              >
                <h5>
                  {{ store.state.strings.nodes_feedback }}
                </h5>
              </label>
              <textarea 
                id="exampleFormControlTextarea1" 
                v-model="feedback.feedback" 
                class="form-control"
                :placeholder="store.state.strings.nodes_no_feedback"
                rows="5" 
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <Handle 
      id="source_feedback" 
      type="source" 
      :position="Position.Bottom" 
      :style="handleStyle"
    />
  </div>
</template>

<style scoped>
.custom-node {
  background-color: #6495ED;
  padding: 10px;
  border: 1px solid #ccc;
  opacity: 0.5;
}

.has-text {
  opacity: 1;
}
</style>
