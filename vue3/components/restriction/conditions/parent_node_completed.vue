<template>
  <div class="form-check">
    {{ restriction.description }}
    <div v-if="parentNodes">
      {{ store.state.strings.restriction_parents_found }}
      <div 
        v-for="(value, key) in parentNodes" 
        :key="key" 
        class="card-text"
      >
        <div class="fullname-container">
          {{ value.name }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useStore } from 'vuex';

const store = useStore()

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  }, 
  restriction: {
    type: Object,
    required: true,
  },
  learningpath: {
    type: Object,
    required: true,
  }
})

const parentNodes = ref([])

onMounted(() => {
  props.learningpath.json.tree.nodes.forEach(node => {
    if (node.childCourse.includes(store.state.node.node_id)) {
      parentNodes.value.push({
        id: node.id,
        name: node.data.fullname
      });
    }
  })
});
</script>

<style scoped>

.card-text {
  padding: 5px;
}

.fullname-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #f0f0f0; /* Set your desired background color */
  padding: 10px; /* Adjust padding as needed */
  border-radius: 10px; /* Set your desired border-radius */
}

</style>