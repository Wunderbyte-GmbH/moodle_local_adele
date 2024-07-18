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
  <div
    v-if="node"
    class="card"
  >
    <button @click="goBack(null)" class="back-button">{{store.state.strings.mobile_view_detail_back}}</button>
    <div class="card-header">
      <h2>{{ node.data.fullname }}</h2>
    </div>
    <div class="card-body">
      <p v-if="node.id">
        <strong>{{store.state.strings.mobile_view_detail_id}}</strong> {{ node.id }}
      </p>
      <p v-if="node.data.description"><strong>{{store.state.strings.mobile_view_detail_description}}</strong> {{node.data.description}}</p>
      <p v-if="node.data.estimate_duration"><strong>
        {{store.state.strings.mobile_view_detail_estimate}}
      </strong> {{node.data.mobile_view_detail_course_link}}</p>
      <p><a href="details.link" target="_blank" rel="noopener noreferrer">
        {{store.state.strings.mobile_view_detail_estimate}}
      </a></p>
      <UserInformation
        :data="node.data"
      />
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useStore } from 'vuex'
import UserInformation from '../../nodes_items/UserInformation.vue';


const store = useStore()
const props = defineProps({
  details: {
    type: String,
    required: true,
  }
});

const node = ref(null)
const feedback = ref(null)

onMounted(() => {
  const json = store.state.lpuserpathrelation.json
  if (json.tree.nodes) {
    json.tree.nodes.forEach((user_node) => {
      if (user_node.id == props.details) {
        node.value = user_node
      }
    })
  }
  if (json.user_path_relation) {
    Object.entries(json.user_path_relation).forEach(([key, user_feedback]) => {
      if (key == props.details) {
        feedback.value = user_feedback;
      }
    });
  }
})


// Emit to parent component
const emit = defineEmits([
  'changed-details',
]);

// Function to select a tab
const goBack = (details) => {
  emit('changed-details', details);
};
</script>

<style scoped>
.card {
  border: 1px solid #ccc;
  border-radius: 8px;
  padding: 16px;
  max-width: 400px;
  margin: 0 auto;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.back-button {
  display: block;
  margin-bottom: 16px;
  padding: 8px 16px;
  background-color: #f5f5f5;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.card-header {
  margin-bottom: 16px;
}

.card-header h2 {
  margin: 0;
}

.card-body p {
  margin: 8px 0;
}

.card-body a {
  color: #007bff;
  text-decoration: none;
}

.card-body a:hover {
  text-decoration: underline;
}
</style>