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
import { onMounted, ref, watch } from 'vue'
// Import needed libraries
import { Handle, Position } from '@vue-flow/core'

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const minTextareaHeight = 3 // Minimum number of lines
const lineHeight = 20 // Height of one line in pixels (adjust as needed)

const calculateExpandedHeight = () => {
    // Calculate the number of lines in the textarea content
    let numberOfLines = props.data.feedback.split('\n').length

    // Calculate the number of lines based on the total number of characters
    const numberOfCharacters = props.data.feedback.length
    const charactersPerLine = 25 // Adjust as needed based on your content
    if(numberOfLines < Math.ceil(numberOfCharacters / charactersPerLine)){
        numberOfLines = Math.ceil(numberOfCharacters / charactersPerLine)
    }
  // Set the height based on the content, with a minimum of 3 lines
  textareaHeight.value = `${Math.max(minTextareaHeight, numberOfLines) * lineHeight}px`
}

const handleFocus = () => {
  // Set the expanded height when focused
  calculateExpandedHeight()
}

const handleBlur = () => {
  // Collapse the textarea to the minimum height when focus is lost
  textareaHeight.value = `${minTextareaHeight * lineHeight}px`
}

// Set the initial height to the minimum height
const textareaHeight = ref(`${minTextareaHeight * lineHeight}px`)

// Watch for changes in feedbackText and trigger calculateExpandedHeight
watch(() => props.data.feedback, calculateExpandedHeight)

const formattingState = ref({
  italic: false,
  bold: false,
  code: false,
});

const toggleFormatting = (format) => {
  formattingState.value[format] = !formattingState.value[format];
  applyFormatting();
};

const isFormattingActive = (format) => formattingState.value[format];

const applyFormatting = () => {
  let formattedText = props.data.feedback;

  if (formattingState.value.italic) {
    formattedText = `*${formattedText}*`;
  }

  if (formattingState.value.bold) {
    formattedText = `**${formattedText}**`;
  }

  if (formattingState.value.code) {
    formattedText = `\`${formattedText}\``;
  }
  props.data.feedback = formattedText;
  calculateExpandedHeight();
};

</script>

<template>
  <div :class="{ 'custom-node': true, 'has-text': props.data.feedback }" 
    class="custom-node text-center rounded p-3" 
    style="width: 350px; height: 150px;"
    >
    <p style="margin-bottom: 0px;">
        Feedback
    </p>
    <div class="formatting-toolbar">
      <button @click="toggleFormatting('italic')" :class="{ active: isFormattingActive('italic') }">
        <i class="fa fa-italic"></i>
      </button>
      <button @click="toggleFormatting('bold')" :class="{ active: isFormattingActive('bold') }">
        <i class="fa fa-bold"></i>
      </button>
      <button @click="toggleFormatting('code')" :class="{ active: isFormattingActive('code') }">
        <i class="fa fa-code"></i>
      </button>
    </div>
    <textarea
      v-model="props.data.feedback"
      @focus="handleFocus"
      @blur="handleBlur"
      :style="{ height: textareaHeight }"
      class="custom-textarea"
      placeholder="<b>Type your feedback here...</b>"
    ></textarea>
  </div>
  <Handle id="source_feedback" type="source" :position="Position.Bottom" />
</template>

<style scoped>

.formatting-toolbar {
  margin-bottom: 3px;
  display: flex;
  justify-content: center;
}

.formatting-toolbar button {
  background-color: #4CAF50;
  border: 1px solid #45a049;
  color: white;
  margin: 2px;
  padding: 3px;
  cursor: pointer;
  transition: background-color 0.3s;
}

.formatting-toolbar button:hover {
  background-color: #45a049;
}

.formatting-toolbar button.active {
  background-color: #3498db;
}
.custom-node {
  background-color: #6495ED;
  padding: 10px;
  border: 1px solid #ccc;
  opacity: 0.5;
}

.has-text {
  opacity: 1;
}

.custom-textarea {
  width: 100%;
  box-sizing: border-box;
  resize: none; /* Disable textarea resizing */
  overflow: hidden; 
}
</style>
