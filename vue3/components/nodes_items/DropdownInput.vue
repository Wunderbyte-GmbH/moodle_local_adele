<template>
  <div>
    <input
      v-model="testSearch"
      type="text"
      class="form-control mb-3"
      placeholder="Search tests"
      @focus="showDropdown = true"
      @blur="handleBlur"
    >
    <div v-if="showDropdown" class="dropdown" ref="dropdown" @mousedown="handleDropdownClick">
      <ul class="dropdown-list" @scroll.prevent="preventScroll">
        <li @mousedown="selectOption(null)">
          <div class="test-info">
            <div>
              <b>{{ store.state.strings.nodes_items_none }}</b>
            </div>
          </div>
        </li>
        <li
          v-for="option in filteredTests"
          :key="option.id"
          @mousedown="selectOption(option)"
        >
          <div class="test-info">
            <div>
              <b>{{ store.state.strings.nodes_items_testname }}</b>
              {{ option.name }}
            </div>
            <div>
              <b>{{ store.state.strings.nodes_items_coursename }}</b>
              {{ option.coursename }}
            </div>
          </div>
        </li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import { useStore } from 'vuex';

interface Test {
  id: string;
  name: string;
  coursename: string;
}

const store = useStore();
const showDropdown = ref<boolean>(false);
const selectedTest = ref<Test | null>(null);
const emit = defineEmits<{
  (event: 'update:value', value: Test | null): void;
}>();
const testSearch = ref<string | null>('');
let dropdownClicked = false;

const props = defineProps<{
  selectedTestId: string | null;
  tests: Test[];
}>();

// Handle blur only if the click is outside the dropdown
const handleBlur = () => {
  setTimeout(() => {
    if (!dropdownClicked) {
      showDropdown.value = false;
    }
    dropdownClicked = false; // Reset flag
  }, 200);
};

// Detect clicks inside the dropdown or scrollbar
const handleDropdownClick = () => {
  dropdownClicked = true; // Mark dropdown as clicked to prevent closing
};

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
}, { deep: true });

// watch values from selected node
watch(() => selectedTest.value, () => {
  emit('update:value', selectedTest.value);
}, { deep: true });

const filteredTests = computed(() => {
  const searchTerm = testSearch.value ? testSearch.value.toLowerCase() : '';
  return props.tests.filter((test) => {
    const testName = test.name ? test.name.toLowerCase() : '';
    const courseName = test.coursename ? test.coursename.toLowerCase() : '';
    return testName.includes(searchTerm) || courseName.includes(searchTerm);
  });
});

const selectOption = (option: Test | null) => {
  selectedTest.value = option;
  if (option) {
    testSearch.value = option.name;
  } else {
    testSearch.value = null;
  }
  showDropdown.value = false;
};

const preventScroll = (event: Event) => {
  const element = event.target as HTMLElement;
  if (element.scrollHeight > element.clientHeight) {
    event.preventDefault();
  }
};

</script>

<style scoped>
.dropdown {
  position: absolute;
  width: 90%;
  background-color: aliceblue;
  z-index: 100;
}

.dropdown-list {
  list-style-type: none;
  padding: 0;
  max-height: 200px;
  margin: 0;
  overflow-y: auto;
  /* Indicate scrollable content */
  border: 1px solid #ccc;
  scrollbar-width: thin; /* For Firefox */
}

.dropdown-list::-webkit-scrollbar {
  width: 8px; /* For Chrome/Safari */
}

.dropdown-list::-webkit-scrollbar-thumb {
  background-color: #888; /* Darker scrollbar for visibility */
  border-radius: 4px;
}

.dropdown-list::-webkit-scrollbar-thumb:hover {
  background-color: #555; /* Even darker on hover */
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
