<template>
  <div class="form-check">
    {{ restriction.description }}
    <div class="form-group">
      <label
        class="form-label"
        for="courseSelect"
      >
        {{ store.state.strings.restriction_select_course }}
      </label>
      <select
        id="courseSelect"
        v-model="selectedCourse"
        class="form-select"
      >
        <option
          :value="null"
          disabled
        >
          {{ store.state.strings.restriction_select_course }}
        </option>
        <option
          v-for="course in courses"
          :key="course.id"
          :value="course.id"
        >
          {{ course.name }}
        </option>
      </select>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';

// Load Store
const store = useStore();
const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  restriction: {
    type: Object,
    required: true,
  }
})
const data = ref([])
const courses = ref([])
const selectedCourse = ref(null)
const emit = defineEmits(['update:modelValue'])

onMounted(async () => {
  store.state.learningpath.json.tree.nodes.forEach(node => {
    if (store.state.node.id != node.id) {
      courses.value.push({
        id: node.id,
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