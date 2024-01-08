<template>
  <button @click="goBack" class="btn btn-outline-primary">
    <i class="fa fa-arrow-left"></i> Go Back to Learningpath
  </button>

  <h3>Edit Restrictions to enter course node</h3>
  <div class="card">
  <div class="card-body">
    <h5 class="card-title">
        <i class="fa fa-check-circle"></i>Restrictions for:
    </h5>
    <ul class="list-group list-group-flush">
        <li class="list-group-item">
            <i class="fa fa-header"></i> Course Title: {{ store.state.node.fullname }}
        </li>
        <li class="list-group-item">
            <i class="fa fa-tag"></i> Tags: {{ store.state.node.tags }}
        </li>
    </ul>
  </div>

  <div v-if="restrictions !== null">
    <ParentNodes :parentNodes="parentNodes" />

    <ChildNodes :childNodes="childNodes" />
  </div>
  <div v-else>
      Loading restrictions...
  </div>
  </div>
</template>
<script setup>
// Import needed libraries
import { ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import ChildNodes from '../charthelper/childNodes.vue'
import ParentNodes from '../charthelper/parentNodes.vue'

// Load Store 
const store = useStore();

// Get all available restrictions
const restrictions = ref(null);

// Intersected node
const parentNodes = ref([]);
const childNodes = ref([]);

onMounted(async () => {
    try {
      restrictions.value = await store.dispatch('fetchCompletions');
    } catch (error) {
        console.error('Error fetching completions:', error);
    }
    const learningGoal = store.state.learninggoal[0];
    if (learningGoal && learningGoal.json && learningGoal.json.tree && learningGoal.json.tree.nodes) {
        learningGoal.json.tree.nodes.forEach((node) => {
            if (node.childCourse && node.childCourse.includes(store.state.node.node_id)) {
                parentNodes.value.push(node);
            } else if (node.parentCourse && node.parentCourse.includes(store.state.node.node_id)) {
                childNodes.value.push(node);
            }
        });
    }
});

// Function to go back
const goBack = () => {
  store.state.editingadding = !store.state.editingadding
  store.state.editingrestriction = !store.state.editingrestriction
}

</script>