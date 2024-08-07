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
  <div class="table-container">
    <table class="table table-margin-top">
      <thead>
        <tr>
          <th @click="sortTable('id')" :class="getSortClass('id')">{{ store.state.strings.user_view_id }}</th>
          <th @click="sortTable('username')" :class="getSortClass('username')">{{ store.state.strings.user_view_username }}</th>
          <th @click="sortTable('firstname')" :class="getSortClass('firstname')">{{ store.state.strings.user_view_firstname }}</th>
          <th @click="sortTable('lastname')" :class="getSortClass('lastname')">{{ store.state.strings.user_view_lastname }}</th>
          <th @click="sortTable('progress.progress')" :class="getSortClass('progress.progress')">{{ store.state.strings.user_view_progress }}</th>
          <th @click="sortTable('progress.completed_nodes')" :class="getSortClass('progress.completed_nodes')">{{ store.state.strings.user_view_nodes }}</th>
        </tr>
      </thead>
        <transition-group name="list" tag="tbody">
          <tr
            v-for="relation in sortedRelations"
            :key="relation.id"
            :class="{ 'highlighted-row': relation.id === focusEntry }"
          >
            <td>
              <router-link
                v-if="store.state.view !== 'student'"
                :to="{ name: 'userDetails', params: { learningpathId: store.state.learningPathID, userId: relation.id }}"
              >
                {{ relation.id }}

              </router-link>
              <div v-else>
                {{ relation.id }}
              </div>
            </td>
            <td>{{ relation.username }}</td>
            <td>{{ relation.firstname }}</td>
            <td>{{ relation.lastname }}</td>
            <td>
              <ProgressBar :progress="relation.progress.progress" />
            </td>
            <td>{{ relation.progress.completed_nodes }}</td>
          </tr>
        </transition-group>
    </table>
  </div>
</template>

<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';
import ProgressBar from '../nodes_items/ProgressBar.vue';

// Load Store
const store = useStore();
const sortedRelations = ref([...store.state.lpuserpathrelations.slice(0, 10)]);
const sortKey = ref('');
const sortDirection = ref(1);
const focusEntry = ref(null);


const stop = watch(
  () => store.state.lpuserpathrelations,
  (newVal) => {
    if (store.state.view === 'student' && store.state.userlist === 2) {
      sortedRelations.value = newVal.filter(obj => obj.id === store.state.user);
    } else {
      sortedRelations.value = [...newVal];
    }

    if (store.state.view === 'student') {
      focusEntry.value = store.state.lpuserpathrelation.user_id;
    }
    scrollIntoFocus()
  },
);

onMounted(() => {
  scrollIntoFocus()
});

const scrollIntoFocus = () => {
  nextTick(() => {
    if (focusEntry.value) {
      const row = document.querySelector(`.highlighted-row`);
      if (row) {
        // Get the offsetTop of the row relative to the table container
        const container = document.querySelector('.table-container');
        const rowOffsetTop = row.offsetTop;
        const containerHeight = container.clientHeight;
        const rowHeight = row.clientHeight;
        const scrollTop = rowOffsetTop - (containerHeight / 2) + (rowHeight / 2);
        container.scrollTo({ top: scrollTop, behavior: 'smooth' });
      }
    }
  });
}

const sortTable = (key) => {
  if (sortKey.value === key) {
    sortDirection.value *= -1;
  } else {
    sortKey.value = key;
    sortDirection.value = 1;
  }
  sortedRelations.value.sort((a, b) => {
    const aValue = key.split('.').reduce((o, i) => o[i], a);
    const bValue = key.split('.').reduce((o, i) => o[i], b);
    return (aValue > bValue ? 1 : -1) * sortDirection.value;
  });
};

const getSortClass = (key) => {
  return {
    sortable: true,
    sorted: sortKey.value === key,
    ascending: sortKey.value === key && sortDirection.value === 1,
    descending: sortKey.value === key && sortDirection.value === -1,
  };
};
</script>

<style scoped>

.list-move,
.list-enter-active,
.list-leave-active {
  transition: all 1.5s ease;
}

.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: translateY(30px);
}

.list-leave-active {
  position: absolute;
}

.table-container {
  max-height: 500px;
  overflow-y: auto;
}

.table-margin-top {
  margin-top: 5rem;
  border-collapse: collapse;
  width: 100%;
}

thead tr {
  position: sticky;
  top: 0;
  background-color: white;
  z-index: 1;
}

th {
  cursor: pointer;
  position: relative;
  transition: background-color 0.3s, color 0.3s;
}

th.sortable::after {
  content: ' ⇅';
  font-size: 0.8em;
  color: #aaa;
}

th.sorted.ascending::after {
  content: ' ↑';
  color: #333;
}

th.sorted.descending::after {
  content: ' ↓';
  color: #333;
}

tbody tr {
  transition: background-color 0.3s;
}

tbody tr:hover {
  background-color: #f5f5f5;
}

.progress {
  height: 20px;
  overflow: hidden;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.progress-bar {
  text-align: center;
  line-height: 20px;
  color: #fff;
  border-radius: 10px;
}

tbody tr.highlighted-row {
  background-color: #e0f7fa;
  transition: background-color 0.3s;
}

</style>