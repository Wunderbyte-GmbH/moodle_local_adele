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

<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { useStore } from 'vuex';

const store = useStore();
const showDropdown = ref(false);
const selectedTest = ref(null);
const emit = defineEmits(['update:value']);
const testSearch = ref('');
let dropdownClicked = false;

const props = defineProps({
  selectedTestId: {
    type: String,
    default: null,
  },
  tests: {
    type: Object,
    required: true,
  }
});

// Handle blur only if the click is outside the dropdown
const handleBlur = (event) => {
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
  if (option) {
    testSearch.value = option.name;
  } else {
    testSearch.value = null;
  }
  showDropdown.value = false;
};

const preventScroll = (event) => {
  const element = event.target;
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
