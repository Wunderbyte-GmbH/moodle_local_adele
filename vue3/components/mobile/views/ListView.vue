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
  <div class="todo-list">
    <div class="header">
      <h1>
        <i class="fa-solid fa-list" />
        {{store.state.strings.mobile_view_list_header}}
      </h1>
      <div class="button-group">
        <button @click="filterTasks" title="Filter">
          <i class="fas fa-filter" />
        </button>
        <button @click="sortTasks" title="Sort">
          <i class="fas fa-sort" />
        </button>
      </div>
    </div>
    <ul>
      <li
        v-for="(task) in tasks"
        :key="task.id"
        :class="task.type"
        :style="task.color ? { backgroundColor: task.color + '10' } : {}"
        @click="changeDetails(task)"
      >
        <span :class="task.priority">{{ task.text }}</span>
        <span class="icons">
          <i class="fas fa-link" />
        </span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref } from 'vue';

import { useStore } from 'vuex'
import { onMounted } from 'vue';


const store = useStore()
const tasks = ref([]);


onMounted(() => {
  if (store.state.lpuserpathrelation.json.tree.nodes) {
    const nodes = store.state.lpuserpathrelation.json.tree.nodes
    const modules = store.state.lpuserpathrelation.json.modules
    nodes.forEach((node) => {
      let color = null;
      if (
        typeof node.data.module === 'number' &&
        !isNaN(node.data.module)
      ) {
        modules.forEach((module) => {
          if (module.id == node.data.module) {
            color = module.color
          }
        })
      }
      const text = node.data.fullname || 'Collection';
      tasks.value.push({
        id: node.id,
        text,
        type: node.type,
        color: color,
      });
    })
  }
})

// Emit to parent component
const emit = defineEmits([
  'changed-details',
]);

// Function to select a tab
const changeDetails = (details) => {
  emit('changed-details', details.id);
};
</script>

<style scoped>
.todo-list {
  font-family: Arial, sans-serif;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem;
  border-bottom: 1px solid #ccc;
}

.header h1 {
  font-size: 1.5rem;
  margin: 0;
}

.header button {
  font-size: 1.5rem;
  background: none;
  border: none;
  cursor: pointer;
}

ul {
  list-style: none;
  padding: 0;
  margin: 0;
}

li {
  display: flex;
  align-items: center;
  padding: 0.5rem;
  border-bottom: 1px solid #ccc;
}

li.orcourses {
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  margin-top: 2px;
  margin-left: 10px;
}

li .icons {
  margin-left: auto;
}

li .icons i {
  margin-left: 0.5rem;
}

</style>
