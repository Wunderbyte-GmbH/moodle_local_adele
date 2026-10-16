<!--
// This file is part of Moodle - http://moodle.org/
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
-->

<!--
// Keyboard route into the graph (#575 B5).
//
// The editor builds the path with HTML5 drag-and-drop, which needs a pointer
// and a dragging movement (WCAG 2.1.1 Keyboard; 2.5.7 Dragging Movements in
// 2.2). This dialog asks for the two things the pointer would otherwise
// express by position - WHICH node to attach to and HOW - and then hands them
// to the same insertion routine the drop handler uses.
//
// It is a dialog, not a panel: the graph behind it is not operable while it
// is open, so the focus stays inside (Tab wraps) and returns to the control
// that opened it. Escape cancels.
//
// @package     local_adele
// @copyright   2026 Wunderbyte GmbH
// @copyright   2026 Ralf Erlebach
// @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
-->

<template>
  <div
    class="local-adele-dialog-backdrop"
    @keydown.esc.stop.prevent="cancel"
    @keydown.tab="trapFocus"
  >
    <div
      ref="dialog"
      class="card local-adele-dialog"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      data-testid="learningpath-insert-dialog"
    >
      <div class="card-body">
        <h2 class="h5">{{ title }}</h2>

        <div class="form-group">
          <label for="local-adele-insert-target">
            {{ store.state.strings.insert_dialog_target }}
          </label>
          <select
            id="local-adele-insert-target"
            ref="firstField"
            v-model="targetId"
            class="form-control"
            data-testid="learningpath-insert-target"
          >
            <option
              v-for="option in targets"
              :key="option.id"
              :value="option.id"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="form-group">
          <label for="local-adele-insert-relation">
            {{ store.state.strings.insert_dialog_relation }}
          </label>
          <select
            id="local-adele-insert-relation"
            v-model="relation"
            class="form-control"
            data-testid="learningpath-insert-relation"
          >
            <option
              v-for="option in relations"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="d-flex justify-content-end">
          <button
            type="button"
            class="btn btn-secondary m-1"
            data-testid="learningpath-insert-cancel"
            @click="cancel"
          >
            {{ store.state.strings.btncancel }}
          </button>
          <button
            ref="lastField"
            type="button"
            class="btn btn-primary m-1"
            data-testid="learningpath-insert-confirm"
            @click="confirm"
          >
            {{ store.state.strings.insert_dialog_confirm }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onBeforeUnmount, ref } from 'vue';
import { useStore } from 'vuex';

const store = useStore();

const props = defineProps({
  /** The course the sidebar offered. */
  course: {
    type: Object,
    required: true,
  },
  /** The nodes currently in the graph. */
  nodes: {
    type: Array,
    required: true,
  },
});

const emit = defineEmits(['insert', 'cancel']);

const dialog = ref(null);
const firstField = ref(null);
const lastField = ref(null);
const opener = ref(null);

const title = computed(() =>
  (store.state.strings.insert_dialog_title || '') + ' ' + (props.course.fullname || ''));

/**
 * The nodes a new course can be attached to.
 *
 * The starting node is offered under its own name: attaching there is what
 * dropping onto the start marker does, and a path has to begin somewhere.
 */
const targets = computed(() => {
  const list = [{ id: 'starting_node', label: store.state.strings.insert_dialog_start }];
  props.nodes.forEach((node) => {
    if (node.id === 'starting_node' || String(node.id).startsWith('dropzone')) {
      return;
    }
    list.push({ id: node.id, label: node.data && node.data.fullname ? node.data.fullname : node.id });
  });
  return list;
});

const relations = computed(() => [
  { value: 'dropzone_child', label: store.state.strings.insert_dialog_after },
  { value: 'dropzone_parent', label: store.state.strings.insert_dialog_before },
  { value: 'dropzone_and', label: store.state.strings.insert_dialog_and },
  { value: 'dropzone_or', label: store.state.strings.insert_dialog_or },
]);

const targetId = ref('starting_node');
const relation = ref('dropzone_child');

/**
 * Keep the focus inside the dialog.
 *
 * Only the two ends need handling: everything between them is reached by the
 * browser's own tab order.
 *
 * @param {KeyboardEvent} event The tab keydown.
 */
const trapFocus = (event) => {
  const first = firstField.value;
  const last = lastField.value;
  if (!first || !last) {
    return;
  }
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
};

const cancel = () => {
  emit('cancel');
};

const confirm = () => {
  emit('insert', {
    course: props.course,
    targetId: targetId.value,
    // Attaching to the starting node is the one case the graph handles by the
    // target alone, so the relation is not asked back there.
    dropzone: targetId.value === 'starting_node' ? 'starting_node' : relation.value,
  });
};

onMounted(async () => {
  opener.value = document.activeElement;
  await nextTick();
  if (firstField.value) {
    firstField.value.focus();
  }
});

onBeforeUnmount(() => {
  // Give the focus back where it came from; otherwise it falls to the top of
  // the document and the user has to find their place again.
  if (opener.value && typeof opener.value.focus === 'function') {
    opener.value.focus();
  }
});
</script>

<style scoped>
.local-adele-dialog-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}

.local-adele-dialog {
  width: min(32rem, 90vw);
  text-align: left;
}
</style>
