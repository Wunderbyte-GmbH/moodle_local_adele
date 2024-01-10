<template>
  <div class="form-check">
    {{ restriction.description }}
    <div class="form-group">
      <label class="form-label" for="courseSelect">Select a Course:</label>
      <select id="courseSelect" class="form-select" v-model="selectedCourse">
        <option :value="null" disabled>Select a Course</option>
        <option v-for="course in courses" :key="course.id" :value="course.id">{{ course.name }}</option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';

// Load Store 
const store = useStore();
const props = defineProps(['modelValue', 'restriction'])
const data = ref([])
const courses = ref([])
const selectedCourse = ref(null)
const emit = defineEmits()

onMounted(async () => {
  store.state.learninggoal[0].json.tree.nodes.forEach(node => {
    if (store.state.node.course_node_id != node.data.course_node_id) {
      courses.value.push({
        id: node.data.course_node_id,
        name: node.data.fullname
      });
    }
  })
  // Set selectedCourse to the value of props.restriction.value
  if (props.restriction.value !== undefined) {
    selectedCourse.value = props.restriction.value.courseid;
  }
});


// Watch for changes in selectedCourse
watch(() => selectedCourse.value, async () => {
  data.value = {
    courseid: selectedCourse.value,
  }
}, { deep: true });

// Watch for changes in data and emit the update
watch(() => data.value, () => {
  emit('update:modelValue', data.value);
}, { deep: true });

</script>

<style scoped>
.form-check {
  margin-bottom: 10px;
}

.form-group {
  margin-bottom: 15px;
}

.form-label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}

.form-select,
.form-control {
  width: 100%; /* Make the inputs fill their container */
  padding: 8px;
  font-size: 14px;
  border: 1px solid #ced4da;
  border-radius: 4px;
}

.form-select {
  max-width: 100%; /* Set a maximum width for the select */
}

/* Add any additional styling as needed */
</style>