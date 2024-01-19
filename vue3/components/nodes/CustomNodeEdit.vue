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
import { defineProps, computed, ref, watch } from 'vue';
import { useStore } from 'vuex';
import CompletionOutPutItem from '../completion/CompletionOutPutItem.vue'
import RestrictionOutPutItem from '../restriction/RestrictionOutPutItem.vue'

// Load Store 
const store = useStore();
const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

// Dynamic background color based on data.completion
const nodeBackgroundColor = computed(() => {
  return {
    backgroundColor: props.data.completion.completionnode.valid ? '#5cb85c' : 'rgba(169, 169, 169, 0.5)',
  };
});

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

const isCompletionVisible = ref(false);
const isRestrictionVisible = ref(false);

const toggleTable = (condition) => {
  const otherCondition = condition == 'Completion' ? 'Restriction' : 'Completion';
  const conditionRef = eval(`is${condition}Visible`);
  conditionRef.value = !conditionRef.value;
  const otherconditionRef = eval(`is${otherCondition}Visible`);
  otherconditionRef.value = false;
};

</script>

<template>
  <div>
    <div 
      class="custom-node text-center rounded p-3"
      :style="[nodeBackgroundColor, { height: '200px', width: '400px' }]"
    >
      <div class="mb-2">
        <b>{{ store.state.strings.node_coursefullname }}</b> {{ data.fullname }}
      </div>
      <div v-if="data.manualrestriction">
        <RestrictionOutPutItem 
          :data="data"
        />
      </div>
      <div v-if="data.manualcompletion">
        <CompletionOutPutItem :data="data" />
      </div>
      <div v-if="data.completion.singlecompletionnode">
        <button 
          class="btn btn-link" 
          aria-expanded="false" 
          aria-controls="collapseTable"
          @click="toggleTable('Completion')"
        >
          {{ isCompletionVisible ? 'Hide Completion Criteria' : 'Show Completion Criteria' }}
        </button>
        <div
          v-show="isCompletionVisible" 
          class="table-container"
        >
          <table class="table table-bordered table-hover fancy-table">
            <thead class="thead-light">
              <tr>
                <th>Key</th>
                <th>Checkmark</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="(value, key) in data.completion.singlecompletionnode" 
                :key="key"
              >
                <td>{{ key }}</td>
                <td>
                  {{ value }}
                  <span 
                    v-if="value" 
                    class="text-success"
                  >
                    &#10004;
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="data.completion.singlerestrictionnode">
        <button 
          class="btn btn-link" 
          aria-expanded="false" 
          aria-controls="collapseTable"
          @click="toggleTable('Restriction')"
        >
          {{ isRestrictionVisible ? 'Hide Restriction Criteria' : 'Show Restriction Criteria' }}
        </button>
        <div 
          v-show="isRestrictionVisible" 
          class="table-container"
        >
          <div v-if="!data.completion.singlerestrictionnode">
            <table class="table table-bordered table-hover fancy-table">
              <thead class="thead-light">
                <tr>
                  <th>Key</th>
                  <th>Checkmark</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="(value, key) in data.completion.singlerestrictionnode" 
                  :key="key"
                >
                  <td>{{ key }}</td>
                  <td>
                    {{ value }}
                    <span 
                      v-if="value" 
                      class="text-success"
                    >
                      &#10004;
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else>
            <div class="card">
              <div class="card-body">
                No conditions are defined
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <Handle 
      id="target" 
      type="target" 
      :position="Position.Top" 
      :style="handleStyle" 
    />
    <Handle 
      id="source" 
      type="source" 
      :position="Position.Bottom" 
      :style="handleStyle" 
    />
  </div>
</template>

<style scoped>
.custom-node {
  padding: 10px;
  border: 1px solid #ccc;
}
.table-hover tbody tr:hover {
  background-color: #f5f5f5;
}
.table-container {
  left: 50%;
  transform: translate(-50%, 0);
  position: absolute;
  z-index: 100;
}

/* Fancy table styles */
.fancy-table {
  border-radius: 10px; /* Rounded corners */
}

.fancy-table thead th {
  background-color: #3498db; /* Header background color */
  color: #fff; /* Header text color */
}

.fancy-table tbody {
  background-color: #ecf0f1; /* Body background color */
}

.fancy-table tbody tr:nth-child(odd) {
  background-color: #d1d1d1; /* Alternate row background color */
}

.fancy-table tbody tr:hover {
  background-color: #bdc3c7; /* Hovered row background color */
}


</style>