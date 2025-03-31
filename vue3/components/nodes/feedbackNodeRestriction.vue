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
    type: Boolean,
    required: true,
  },
});

watch(() => props.visibility, () => {
  renderFeedback('before', true)
}, {deep: true})

const feedback = ref([])
const emit = defineEmits(['updateFeedback'])
const { findNode } = useVueFlow()

onMounted(async () => {
  feedback.value = JSON.parse(JSON.stringify(props.data))
  if (feedback.value.feedback_before_checkmark) {
    renderFeedback('before', true)
  }
});

const toggleVisibility = () => {
  feedback.value.visibility = !feedback.value.visibility;
};

// Connection handles
const handleStyle = computed(() => ({
  backgroundColor: props.data.color,
  filter: 'invert(100%)',
  width: '10px',
  height: '10px'
}))

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
          renderedFeedback += store.state.strings.course_condition_concatination_and
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
    :class="{ 'visibility': feedback.visibility}"
    class="custom-node rounded p-3 has-text"
    style="width: 350px; min-height: 200px;"
  >
    <button
      style="position: absolute; top: 5px; left: 5px; background: none; border: none; z-index: 100;"
      @click="toggleVisibility"
    >
      <i
        class="fa"
        :class="{
          'fa-eye': feedback.visibility,
          'fa-eye-slash': !feedback.visibility,
          'strikethrough': !feedback.visibility
        }"
      />
    </button>
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
              <input
                id="feedback_before"
                v-model="feedback.feedback_before_checkmark"
                class="form-check-input"
                type="checkbox"
                :disabled="!feedback.visibility"
                @change="renderFeedback('before', true)"
              >
              <label for="feedback_before">
                {{store.state.strings.nodes_feedback_use_default}}
              </label>
              <textarea
                id="exampleFormControlTextarea1"
                v-model="feedback.feedback_before"
                class="form-control"
                style="resize: none;"
                :placeholder="store.state.strings.nodes_no_feedback"
                rows="5"
                :disabled="feedback.feedback_before_checkmark ||!feedback.visibility"
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
  background-color: #e0e4ec;
  padding: 10px;
  border: 1px solid #ccc;
  opacity: 0.5;
  transition: background-color 0.3s ease;
}

.visibility {
  background-color: #6495ED !important;
  opacity: 1;
}

.has-text {
  opacity: 1;
}
</style>
