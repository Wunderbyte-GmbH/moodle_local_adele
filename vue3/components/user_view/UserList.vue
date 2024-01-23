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
  <table class="table table-margin-top">
    <thead>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Firstname</th>
        <th>Lastname</th>
        <th>Progress</th>
        <th>Nodes</th>
      </tr>
    </thead>
    <tbody>
      <tr 
        v-for="relation in store.state.lpuserpathrelations" 
        :key="relation.id"
      >
        <td>
          <router-link 
            v-if="store.state.view!='student'"
            :to="{ name: 'userDetails', params: { learninggoalId: store.state.learningGoalID, userId: relation.id }}"
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
          <div class="progress">
            <div
              class="progress-bar"
              role="progressbar"
              :style="{ width: relation.progress.progress + '%' }"
              aria-valuenow="{{ relation.progress.progress }}"
              aria-valuemin="0"
              aria-valuemax="100"
            >
              {{ relation.progress.progress }}%
            </div>
          </div>
        </td>
        <td>{{ relation.progress.completed_nodes }}</td>
      </tr>
    </tbody>
  </table>
</template>

<script setup>
import { useStore } from 'vuex'

// Load Store 
const store = useStore()

</script>

<style scoped>
.table-margin-top{
  margin-top: 5rem;
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

</style>