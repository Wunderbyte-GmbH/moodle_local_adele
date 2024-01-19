<template>
  <div>
    <input
      v-model="testSearch"
      type="text"
      class="form-control mb-3"
      placeholder="Search tests"
      @focus="showDropdown = true"
    >
    <div v-if="showDropdown" class="dropdown">
      <ul class="dropdown-list">
        <li
          v-for="option in filteredTests"
          :key="option.id"
          @mousedown.prevent="selectOption(option)"
        >
          <div class="test-info">
            <div><b>Testname:</b> {{ option.name }}</div>
            <div><b>Coursename:</b> {{ option.coursename }}</div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

// Load Store 
const showDropdown = ref(false)
const selectedTest = ref(null)
const emit = defineEmits(['update:value'])
const testSearch = ref('');

const props = defineProps({
  selectedTestId: {
    type: String,
    default: null,
  },
  tests:{
    type: Object,
    required: true,
  }
})

onMounted(() => {
  if (props.selectedTestId) {
    const preselectedTest = props.tests.find((test) => test.id === props.selectedTestId);
    if (preselectedTest) {
      selectedTest.value = preselectedTest;
      testSearch.value = preselectedTest.name;
    }
  }
});

// watch values from selected node
watch(() => props.selectedTestId, () => {
  const preselectedTest = props.tests.find((test) => test.id === props.selectedTestId);
  if (preselectedTest) {
    selectedTest.value = preselectedTest;
    testSearch.value = preselectedTest.name;
  }
}, { deep: true } );

// watch values from selected node
watch(() => selectedTest.value, () => {
  emit('update:value', selectedTest.value);
}, { deep: true } );

const filteredTests = computed(() => {
  let searchTerm = '';
  if (testSearch.value != undefined) {
    searchTerm = testSearch.value.toLowerCase();
  }
  return props.tests.filter((test) =>
    test.name.toLowerCase().includes(searchTerm) ||
    test.coursename.toLowerCase().includes(searchTerm)
  );
});

const selectOption = (option) => {
  selectedTest.value = option;
  testSearch.value = option.name;
  showDropdown.value = false;
}

</script>

<style scoped>

.dropdown {
  position: absolute;
  width: 90%;
  background-color: aliceblue;
}

.dropdown-list {
  list-style-type: none;
  padding: 0;
  max-height: 200px;
  margin: 0;
  overflow-y: auto;
}

.dropdown-list li {
  padding: 8px;
  cursor: pointer;
  border: 1px solid #ccc;
  border-radius: 4px;
  margin-bottom: 4px;
}

.dropdown-list li:hover {
  background-color: #f0f0f0;
}

.test-info {
  display: flex;
  flex-direction: column;
}

</style>