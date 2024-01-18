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
import { computed } from 'vue';
import { useStore } from 'vuex';

// Load Store 
const store = useStore();

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

// Set node data for the modal
const setFeedbackModal = () => {
  store.state.feedback = props.data
};

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

const processedFeedback = computed(() => {
  const maxTextLength = 150; // Set your desired maximum text length
  const feedback = props.data.feedback;

  if (feedback && feedback.length > maxTextLength) {
    // If the feedback exceeds the maximum length, truncate it and add ellipsis
    return feedback.slice(0, maxTextLength) + '...';
  } else {
    return feedback;
  }
});

</script>

<template>
  <div
    :class="{ 'custom-node': true, 'has-text': props.data.feedback }"
    class="custom-node rounded p-3"
    style="width: 350px; height: 200px;"
  >
    <div class="text-center">
      <h5 class="mb-1">
        Feedback
      </h5>

      <div 
        v-if="processedFeedback" 
        class="feedback-section"
      >
        <div v-html="processedFeedback" />
      </div>

      <div 
        v-else 
        class="no-feedback-section"
      >
        <p class="text-muted">
          No feedback set...
        </p>
      </div>

      <div>
        <button
          type="button"
          class="btn btn-secondary m-2"
          data-toggle="modal"
          data-target="#feedbackModal"
          style="opacity: 1 !important;"
          @click="setFeedbackModal"
        >
          Edit Feedback
        </button>
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

.feedback-section {
  background-color: #f8f9fa; /* Set your desired background color */
  padding: 3px;
  border-radius: 5px;
  margin-bottom: 5px;
}

.no-feedback-section {
  background-color: #f8f9fa; /* Set your desired background color */
  padding: 10px;
  border-radius: 5px;
  margin-bottom: 10px;
}

</style>
