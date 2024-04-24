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
import {  useVueFlow } from '@vue-flow/core'
import { computed, onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';

const store = useStore()

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  learningpath: {
    type: Object,
    required: true,
  },
  visibility: {
    type: Object,
    required: true,
  },
});

watch(() => props.visibility, () => {
  renderFeedback('before', true)
  renderFeedback('after', true)
}, {deep: true})

const feedback = ref([])
const emit = defineEmits(['updateFeedback'])
const { findNode } = useVueFlow()

onMounted(async () => {
  feedback.value = JSON.parse(JSON.stringify(props.data))
  if (feedback.value.feedback_before_checkmark) {
    renderFeedback('before', true)
  }
  if (feedback.value.feedback_after_checkmark) {
    renderFeedback('after', false)
  }
});

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

const renderFeedback = (action, emitting) => {
  const checked = action == 'before' ? feedback.value.feedback_before_checkmark : feedback.value.feedback_after_checkmark
  if (checked) {
    let renderedFeedback = ''
    const start_node = findNode(feedback.value.childCondition)
    let nextNode = null;
    if (start_node.data.visibility) {
      renderedFeedback += start_node.data['description_' + action]
    }
    if (start_node.childCondition) {
      if (typeof(start_node.childCondition) == 'string') {
        start_node.childCondition = [start_node.childCondition]
      }
      start_node.childCondition.forEach((childCondition) => {
        if (!childCondition.includes('feedback')) {
          nextNode = childCondition
        }
      })
    }
    while(nextNode) {
      nextNode = findNode(nextNode)
      if (nextNode && nextNode.data.visibility) {
        if (renderedFeedback != '') {
          renderedFeedback += ', '
        }
        renderedFeedback += nextNode.data['description_' + action]
      }
      if (nextNode && nextNode.childCondition) {
        nextNode.childCondition = 
          typeof nextNode.childCondition == 'string' ? [nextNode.childCondition ] : nextNode.childCondition 
        nextNode.childCondition.forEach((childCondition) => {
          if (!childCondition.includes('feedback')) {
            nextNode = childCondition
          }
        })
      } else {
        nextNode = null
      }
    }
    feedback.value['feedback_' + action] = renderedFeedback
  }
  if (emitting) {
    emit('updateFeedback', feedback.value)
  }
}

</script>

<template>
  <div
    :class="{ 'custom-node': true, 'has-text': true}"
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
              <div>
                <label 
                  for="exampleFormControlTextarea1" 
                  class="form-label"
                >
                  <h5>
                    {{ store.state.strings.nodes_feedback }}
                  </h5>
                </label>
              </div>
              <div>
                Before
              </div>
              <input
                id="feedback_before"
                v-model="feedback.feedback_before_checkmark" 
                class="form-check-input"
                type="checkbox"
                @change="renderFeedback('before', true)"
              >
              <label for="feedback_before">
                Use default feedback
              </label>
              <textarea 
                id="exampleFormControlTextarea1" 
                v-model="feedback.feedback_before" 
                class="form-control"
                :placeholder="store.state.strings.nodes_no_feedback"
                rows="5" 
                :disabled="feedback.feedback_before_checkmark"
                @change="emit('updateFeedback', feedback)"
              />
              <div>
                After
              </div>
              <input
                id="feedback_after"
                v-model="feedback.feedback_after_checkmark" 
                class="form-check-input"
                type="checkbox"
                @change="renderFeedback('after', true)"
              >
              <label for="feedback_after">
                Use default feedback
              </label>
              <textarea 
                id="exampleFormControlTextarea1" 
                v-model="feedback.feedback_after" 
                class="form-control"
                :placeholder="store.state.strings.nodes_no_feedback"
                rows="5" 
                :disabled="feedback.feedback_after_checkmark"
                @change="emit('updateFeedback', feedback)"
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
