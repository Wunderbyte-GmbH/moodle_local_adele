<template>
  <div class="form-check">
    {{ completion.description }}
    <DropdownInput 
      :selected-test-id="selectedTest"
      :tests="tests" 
      @update:value="updatedTest"
    />
    <div v-if="parentscales.length > 0">
      <select 
        v-model="selectedparentscale"
        @change="updateScales"
      >
        <option 
          v-for="parentScale in parentscales" 
          :key="parentScale.id" 
          :value="parentScale.id"
        >
          {{ parentScale.name }}
        </option>
      </select>
    </div>
    <div>
      <button 
        v-if="scales.length > 0" 
        class="btn btn-primary rounded-pill"
        @click="toggleTable" 
      >
        {{ showTable ? 'Hide Table' : 'Show Table' }}
      </button>

      <div v-else>
        {{ store.state.strings.conditions_no_scales }}
      </div>
      <div 
        v-if="showTable" 
        class="mt-3"
      >
        <table class="table table-bordered table-striped bg-white">
          <thead class="thead-light">
            <tr>
              <th>
                {{ store.state.strings.conditions_name }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="scale in scales" 
              :key="scale.id"
              :class="[
                (scale.scale > 0 || scale.attempts > 0) ? 'green-row' : 'empty-row'
              ]"
            >
              <td class="position-relative">
                <div
                  class="item-container"
                  @click="showDetails(scale.name)"
                >
                  {{ scale.name }}
                  <div 
                    v-if="scale.showDetails" 
                    class="icon-container"
                  >
                    <i class="fa-solid fa-arrow-right" />
                  </div>
                </div>
                <div 
                  v-if="scale.showDetails" 
                  class="dynamic-content-container"
                >
                  <label for="scalevalue">
                    {{ store.state.strings.conditions_scale_value }}
                  </label>
                  <input 
                    id="scalevalue" 
                    v-model="scalevalue" 
                    class="form-control" 
                  >
                  <label 
                    for="attempts" 
                    class="mt-3"
                  >
                    {{ store.state.strings.conditions_attempts }}
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
                    {{ store.state.strings.conditions_set_values }}
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
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { useStore } from 'vuex';
import DropdownInput from '../../nodes_items/DropdownInput.vue'

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
const parentscales = ref([])
const selectedparentscale = ref('')
const selectedparentscale_courseid = ref('')
const scales = ref([])
const selectedTest = ref(null)
const emit = defineEmits(['update:modelValue'])
const showTable = ref(false)
const scalevalue = ref('');
const attempts = ref('');
onMounted(async () => {
  // Get all tests
  tests.value = await store.dispatch('fetchCatquizTests')
  if (props.completion.value !== undefined) {
    data.value = props.completion.value;
    if (props.completion.value.testid !== undefined) {
      selectedTest.value = props.completion.value.testid;
    }
    if (props.completion.value.testid_courseid !== undefined) {
      selectedparentscale_courseid.value = props.completion.value.testid_courseid;
    }
    if (props.completion.value.scales !== undefined) {
      scales.value = props.completion.value.scales;
    }
    if (props.completion.value.parentscales !== undefined) {
      selectedparentscale.value = props.completion.value.parentscales;
      parentscales.value = await store.dispatch('fetchCatquizParentScales')
    }
  }
  // watch values from selected node
  watch(() => selectedTest.value, async () => {
    if (selectedTest.value == 0) {
      parentscales.value = await store.dispatch('fetchCatquizParentScales')
      scales.value = []
      data.value = {
        testid: selectedTest.value,
        testid_courseid: selectedparentscale_courseid.value,
      }
    }else{
      scales.value = await store.dispatch('fetchCatquizScales', {testid: selectedTest.value})
      parentscales.value = []
      data.value = {
        testid: selectedTest.value,
        testid_courseid: selectedparentscale_courseid.value,
        scales: scales.value,
      }
    }
  }, { deep: true } );
});

const updateScales = async () => {
  scales.value = await store.dispatch('fetchCatquizParentScale', {scaleid: selectedparentscale.value})
  data.value.scales = scales.value
  data.value.parentscales = selectedparentscale.value
  data.value.selectedparentscale_courseid = selectedparentscale_courseid.value
}

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
  hideDetails(null)
  const indexToUpdate = data.value.scales.findIndex((scale) => scale.id === id);
  if (indexToUpdate !== -1) {
    data.value.scales[indexToUpdate].scale = scalevalue.value;
    data.value.scales[indexToUpdate].attempts = attempts.value;
  }
}

const updatedTest = (test) => {
  selectedTest.value = test.id;
  selectedparentscale_courseid.value = test.courseid;
}

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

.empty-row {
  cursor: pointer;
  background-color: #e6e5e5 !important;
  transition: background-color 0.3s ease, color 0.3s ease;
}
.empty-row:hover {
  background-color: #b8b8b8 !important;
}

.green-row {
  cursor: pointer;
  background-color: #e0f7d5 !important;
  transition: background-color 0.3s ease, color 0.3s ease;
}
.green-row:hover {
  background-color: #b8e5a1 !important;
}

.icon-container {
    margin-left: auto;
    padding-left: 10px;
}
.item-container {
    display: flex;
    align-items: center;
    cursor: pointer;
}

</style>