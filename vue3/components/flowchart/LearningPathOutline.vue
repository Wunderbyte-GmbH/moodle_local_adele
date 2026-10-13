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
// The learning path as a list (#575 B3, way B).
//
// Vue Flow places the nodes absolutely on a canvas: their order on screen is
// geometry, not sequence, and the edges between them exist only as drawn
// lines. Reading order, tab order and the relation "B requires A" are
// therefore not available non-visually (WCAG 1.3.1, 1.3.2, 2.4.3).
//
// This is the conforming alternate version WCAG allows instead: the same
// content in a linear, keyboard-operable form - every course with its state,
// its prerequisites named as text, and the same link into the course that
// the node's play button offers. It is reachable from the graph view by a
// button, not hidden behind a setting.
//
// The order is the path's own: starting nodes first, then every node whose
// prerequisites have already appeared (a topological order). Nodes in a
// cycle - which the editor should not produce but the data can carry - are
// appended at the end rather than dropped, because a list that silently
// omits a course would be worse than one that lists it out of order.
//
// @package     local_adele
// @copyright   2026 Wunderbyte GmbH
// @copyright   2026 Ralf Erlebach
// @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
-->

<template>
  <section
    class="local-adele-outline"
    :aria-label="store.state.strings.outline_title"
    data-testid="learningpath-outline"
  >
    <h3>{{ store.state.strings.outline_title }}</h3>

    <p v-if="orderedNodes.length === 0">
      {{ store.state.strings.outline_empty }}
    </p>

    <ol v-else class="local-adele-outline-list">
      <li
        v-for="node in orderedNodes"
        :key="node.id"
        :data-testid="'learningpath-outline-node-' + node.id"
        :data-status="node.status"
      >
        <h4 class="h5 mb-1">{{ node.name }}</h4>
        <p class="mb-1">
          <span class="font-weight-bold">{{ store.state.strings.outline_state }}</span>
          {{ node.statusLabel }}
        </p>
        <p class="mb-1">
          <span class="font-weight-bold">{{ store.state.strings.outline_prerequisites }}</span>
          <template v-if="node.prerequisites.length">
            {{ node.prerequisites.join(', ') }}
          </template>
          <template v-else>
            {{ store.state.strings.outline_no_prerequisites }}
          </template>
        </p>
        <p v-if="node.feedback" class="mb-1">
          {{ node.feedback }}
        </p>
        <a
          v-if="node.courseurl"
          :href="node.courseurl"
          target="_blank"
          rel="noopener"
        >
          {{ store.state.strings.outline_open_course }}: {{ node.name }}
        </a>
        <p v-else class="mb-0 text-muted">
          {{ store.state.strings.node_not_accessible }}
        </p>
      </li>
    </ol>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { useStore } from 'vuex';
import { NODE_STATUSES, UNKNOWN_NODE_STATUS } from '../../composables/useNodeStatus';

const store = useStore();

const props = defineProps({
  learningpath: {
    type: Object,
    required: true,
  },
});

/** The raw nodes of the path, whichever shape the caller holds. */
const rawNodes = computed(() => props.learningpath?.json?.tree?.nodes || []);

/**
 * The status of one node, from the same field the graph reads.
 *
 * @param {object} node A node of the tree.
 * @returns {string} One of NODE_STATUSES, or UNKNOWN_NODE_STATUS.
 */
const statusOf = (node) => {
  const status = node?.data?.completion?.feedback?.status;
  return NODE_STATUSES.includes(status) ? status : UNKNOWN_NODE_STATUS;
};

/**
 * The parents of a node, without the synthetic starting node.
 *
 * @param {object} node A node of the tree.
 * @returns {string[]} Ids of the nodes that must come first.
 */
const parentsOf = (node) => (node.parentCourse || []).filter((id) => id !== 'starting_node');

const orderedNodes = computed(() => {
  const nodes = rawNodes.value.filter((node) => node && node.id);
  const byId = {};
  nodes.forEach((node) => {
    byId[node.id] = node;
  });

  // Topological order: repeatedly take the nodes whose prerequisites are
  // already listed. What is left after that sits in a cycle and is appended.
  const placed = new Set();
  const order = [];
  let progress = true;
  while (progress) {
    progress = false;
    nodes.forEach((node) => {
      if (placed.has(node.id)) {
        return;
      }
      const waiting = parentsOf(node).some((id) => byId[id] && !placed.has(id));
      if (!waiting) {
        placed.add(node.id);
        order.push(node);
        progress = true;
      }
    });
  }
  nodes.forEach((node) => {
    if (!placed.has(node.id)) {
      order.push(node);
    }
  });

  const strings = store.state.strings || {};
  const nameOf = (node) => node?.data?.fullname || '';

  return order.map((node) => {
    const status = statusOf(node);
    const courseid = (node.data && node.data.course_node_id) ? node.data.course_node_id[0] : null;
    // The graph opens the course from an accessible or completed node only;
    // the list must not offer more than the graph does.
    const reachable = status === 'accessible' || status === 'completed';
    return {
      id: node.id,
      name: nameOf(node),
      status,
      statusLabel: strings['node_status_' + status] || status,
      prerequisites: parentsOf(node).map((id) => nameOf(byId[id])).filter(Boolean),
      feedback: node?.data?.completion?.feedback?.text || '',
      courseurl: reachable && courseid
        ? store.state.wwwroot + '/course/view.php?id=' + courseid
        : '',
    };
  });
});
</script>

<style scoped>
.local-adele-outline {
  max-width: 60rem;
  margin: 0 auto;
  text-align: left;
}

.local-adele-outline-list > li {
  margin-bottom: 1.5rem;
}
</style>
