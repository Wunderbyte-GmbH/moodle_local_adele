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
const data = ref({
  node_id: [],
});

const parentNodes = ref([])
const emit = defineEmits(['update:modelValue'])

onMounted(() => {
  props.learningpath.json.tree.nodes.forEach(node => {
    if (node.childCourse.includes(store.state.node.node_id)) {
      let fullname = node.data.fullname
      if (fullname == '') {
        fullname = store.state.strings.nodes_collection
      }
      parentNodes.value.push({
        id: node.id,
        name: fullname
      });
      data.value.node_id.push(node.id)
    }
  })
  emit('update:modelValue', data.value);
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