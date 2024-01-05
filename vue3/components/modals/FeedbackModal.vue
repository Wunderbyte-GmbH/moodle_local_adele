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

 <template>
  <div>
  <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="exampleModalLabel">Edit Feedback</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" @click="closeModal">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <div class="btn-toolbar" role="toolbar" aria-label="Formatting Buttons">
                <div class="btn-group me-2" role="group" aria-label="Bold, Italic, Underline, and Code Buttons">
                  <button type="button" class="btn btn-secondary btn-bold border" @click="toggleFormatting('bold')">
                    <i class="fas fa-bold"></i>
                  </button>
                  <button type="button" class="btn btn-secondary btn-italic border" @click="toggleFormatting('italic')">
                    <i class="fas fa-italic"></i>
                  </button>
                  <button type="button" class="btn btn-secondary btn-underline border" @click="toggleFormatting('underline')">
                    <i class="fas fa-underline"></i>
                  </button>
                  <button type="button" class="btn btn-secondary btn-code border" @click="toggleFormatting('code')">
                    <i class="fas fa-code"></i>
                  </button>
                </div>
              </div>
              <div
                id="feedbackContent"
                ref="feedbackContent"
                contenteditable="true"
                class="form-control mt-2 p-2 border"
                @input="handleInput"
                @keyup="adjustContenteditableHeight"
                v-html="initialFeedback"
              >
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal" @click="closeModal">Close</button>
            <button type="button" class="btn btn-primary" @click="saveChanges">Save Changes</button>
          </div>
        </div>
      </div>
  </div>
  </div>
</template>

<script setup>
import { defineProps, onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';

// Load Store 
const store = useStore();

const initialFeedback = ref(null);
const feedbackContent = ref(null);

const props = defineProps(['initialFeedback']);

const toggleFormatting = (format) => {
  const selection = window.getSelection();
  if (format === 'code') {
    const codeWrapper = document.createElement('code');
    const selectedText = selection.toString();
    // Check if the selected text already has the code format
    if (selection.rangeCount > 0 && selection.getRangeAt(0).commonAncestorContainer.parentNode.tagName === 'CODE') {
      document.execCommand('removeFormat', false, null);
    } else {
      codeWrapper.appendChild(document.createTextNode(selectedText));
      document.execCommand('insertHTML', false, codeWrapper.outerHTML);
    }
  } else {
    document.execCommand(format, false, null);
  }
};
// closing modal
const closeModal = () => {
  $('#feedbackModal').modal('hide');
};
// updating changes and closing modal
const saveChanges = () => {
  // Loop over nodes and macht node
  let learninggoal = store.state.learninggoal[0]

  // Serialize the modified DOM back to a string
  const cleanedHtml = cleanFeedback(feedbackContent.value.innerHTML) 
  learninggoal.json.tree.nodes.forEach((node) => {
    if (node.id == store.state.node.node_id) {
      // Find the feedback node.
      node.completion.nodes.forEach((completionnode) => {
        if (completionnode.type == 'feedback' &&
          completionnode.data.childCondition == store.state.feedback.childCondition) {
          completionnode.data.feedback = cleanedHtml;
        }
      })
    }
  });
  learninggoal.json = JSON.stringify(learninggoal.json); 
  store.dispatch('saveLearningpath', learninggoal);
  store.state.feedback.feedback = cleanedHtml
  learninggoal.json = JSON.parse(learninggoal.json); 
  $('#feedbackModal').modal('hide');
};

const cleanFeedback = (feedback) => {
  feedback = feedback.replace(/(id|style)="[^"]*"\s*/g, '');
  return feedback.replace(/(<span\s*\/?>|<\/span>)/gi, '');
};

const adjustContenteditableHeight = () => {
  feedbackContent.value.style.height = 'auto';
  feedbackContent.value.style.height = `${feedbackContent.value.scrollHeight}px`;
};

onMounted(() => {
  //initialFeedback.value = store.state.feedback.feedback
  $('#feedbackModal').on('shown.bs.modal', () => {
    adjustContenteditableHeight();
  });
});

watch(() => store.state.feedback, (newValue) => {
  initialFeedback.value = store.state.feedback.feedback;
});

</script>

<style scoped>
/* Add this style block to your component or globally in your project to style the buttons */
.btn-bold i,
.btn-italic i,
.btn-underline i,
.btn-code i {
  font-size: 1rem;
  margin-top: -2px; /* Adjust the alignment of the icon */
}
</style>