<template>
  <div class="form-check">
    {{ completion.description }}
    <div class="form-group">
      <DropdownInput 
        :selected-test-id="selectedQuiz"
        :tests="quizzes" 
        @update:value="updatedTest"
      />
    </div>
    <div v-if="selectedQuiz">
      <div class="form-group">
        <label 
          class="form-label" 
          for="grade"
        >
          {{ store.state.strings.conditions_min_grad }}
        </label>
        <input 
          id="grade" 
          v-model="grade" 
          class="form-control" 
        >
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';
import DropdownInput from '../../nodes_items/DropdownInput.vue'

// Load Store 
const store = useStore();
const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  }, 
  completion: {
    type: Object,
    default: null,
  }})
const data = ref([])
const quizzes = ref([])
const selectedQuiz = ref(null)
const grade = ref(null)
const emit = defineEmits(['update:modelValue'])

onMounted(async () => {
  // Get all tests
  quizzes.value = await store.dispatch('fetchModQuizzes')
  if (props.completion.value !== undefined) {
    data.value = props.completion.value;
    if (props.completion.value.quizid !== undefined) {
      selectedQuiz.value = props.completion.value.quizid;
    }
    if (props.completion.value.grade !== undefined) {
      grade.value = props.completion.value.grade;
    }
  }
  // watch values from selected node
  watch(() => selectedQuiz.value, async () => {
    data.value = {
      quizid: selectedQuiz.value,
      grade: grade.value,
    }
  }, { deep: true } );
});

watch(() => grade.value, async () => {
  data.value = {
    quizid: selectedQuiz.value,
    grade: grade.value,
  }
}, { deep: true } );

// watch values from selected node
watch(() => data.value, () => {
  emit('update:modelValue', data.value);
}, { deep: true } );

const updatedTest = (test) => {
  selectedQuiz.value = test.id;
}

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

.form-control {
  width: 100%; /* Make the inputs fill their container */
  padding: 8px;
  font-size: 14px;
  border: 1px solid #ced4da;
  border-radius: 4px;
}

</style>