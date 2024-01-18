<template>
  <div class="form-check">
    {{ completion.description }}
    <input
      v-model="testSearch"
      type="text"
      class="form-control mb-3"
      placeholder="Search tests"
    >
    <select 
      v-model="selectedTest" 
      class="form-select mb-3" 
    >
      <option 
        :value="null" 
        disabled
      >
        Select a Test
      </option>
      <option 
        v-for="test in filteredTests" 
        :key="test.id" 
        :value="test.id"
      >
        {{ test.name }}
      </option>
    </select>
    <div>
      <button 
        v-if="scales.length > 0" 
        class="btn btn-primary rounded-pill"
        @click="toggleTable" 
      >
        {{ showTable ? 'Hide Table' : 'Show Table' }}
      </button>

      <div v-else>
        No scales available
      </div>
      <div 
        v-if="showTable" 
        class="mt-3"
      >
        <table class="table table-bordered table-striped bg-white">
          <thead class="thead-light">
            <tr>
              <th>Name</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="scale in scales" 
              :key="scale.id" 
              :class="{ 'dark-row': scale.showDetails, 'green-row': scale.scale > 0 || scale.attempts > 0 }"
            >
              <td class="position-relative">
                <div @click="showDetails(scale.name)">
                  {{ scale.name }}
                </div>
                <div 
                  v-if="scale.showDetails" 
                  class="dynamic-content-container"
                >
                  <label for="scalevalue">Scale value:</label>
                  <input 
                    id="scalevalue" 
                    v-model="scalevalue" 
                    class="form-control" 
                  >
                  <label 
                    for="attempts" 
                    class="mt-3"
                  >
                    Attempts:
                  </label>
                  <input 
                    id="attempts" 
                    v-model="attempts" 
                    class="form-control"
                  >
                  <button 
                    class="btn btn-primary rounded-pill"
                    @click="setValues(scale.id)" 
                  >
                    Set Values
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useStore } from 'vuex';

// Load Store 
const store = useStore();
const props = defineProps({
  modelValue:{
    type: Object,
    default: null,
  },
  completion: {
    type: Object,
    default: null,   
  }})
const data = ref([])
const tests = ref([])
const scales = ref([])
const selectedTest = ref(null)
const emit = defineEmits(['update:modelValue'])
const showTable = ref(false)
const scalevalue = ref('');
const attempts = ref('');
const testSearch = ref('');

onMounted(async () => {
  // Get all tests
  tests.value = await store.dispatch('fetchCatquizTests')
  if (props.completion.value !== undefined) {
    data.value = props.completion.value;
    if (props.completion.value.testid !== undefined) {
      selectedTest.value = props.completion.value.testid;
    }
    if (props.completion.value.scales !== undefined) {
      scales.value = props.completion.value.scales;
    }
  }
  // watch values from selected node
  watch(() => selectedTest.value, async () => {
    scales.value = await store.dispatch('fetchCatquizScales', {testid: selectedTest.value})
    console.log('scales.value')
    console.log(scales.value)
    data.value = {
      testid: selectedTest.value,
      scales: scales.value,
    }
  }, { deep: true } );
});

// watch values from selected node
watch(() => data.value, () => {
  emit('update:modelValue', data.value);
}, { deep: true } );

const toggleTable = () => {
  showTable.value = !showTable.value;
  hideDetails('')
};

const showDetails = (name) => {
  hideDetails(name)
  const scale = scales.value.find((s) => s.name === name);
  if (scale) {
    scale.showDetails = !scale.showDetails;
  }
  if (scale.scale) {
    scalevalue.value = scale.scale;
  }else{
    scalevalue.value = '';
  }
  if (scale.scale) {
    attempts.value = scale.attempts;
  }else{
    attempts.value = '';
  }
};

const hideDetails = (name) => {
  scales.value.forEach((detail) => {
    if(detail.name != name){
      detail.showDetails = false
    }
  })
}

const setValues = (id) => {
  const indexToUpdate = data.value.scales.findIndex((scale) => scale.id === id);
  if (indexToUpdate !== -1) {
    data.value.scales[indexToUpdate].scale = scalevalue.value;
    data.value.scales[indexToUpdate].attempts = attempts.value;
  }
}
const filteredTests = computed(() => {
  const searchTerm = testSearch.value.toLowerCase();
  return tests.value.filter((test) =>
    test.name.toLowerCase().includes(searchTerm)
  );
});

</script>

<style scoped>
.dynamic-content-container {
  position: absolute;
  top: 0;
  left: 100%;
  background-color: #fff;
  border: 1px solid #ccc;
  padding: 10px;
  border-radius: 5px;
  margin-left: 10px;
  min-width: 250px;
  z-index: 1;
}

.dark-row {
  background-color: #dcdcdc !important; /* or any other darker color */
}
.green-row {
  background-color: #d0f0c0 !important; /* or any other greenish color */
}

</style>