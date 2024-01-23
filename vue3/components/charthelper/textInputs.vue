<script setup>
import { ref, watch, onMounted } from 'vue';
import { useStore } from 'vuex';

const props = defineProps({
  goal: {
      type: Object,
      default: null,
    },
});
 
// Load Store and Router
const store = useStore()

const emit = defineEmits([
    'change-GoalName',
    'change-GoalDescription',
]);

// Define constants that will be referenced
const goalname = ref('')
const goaldescription = ref('')

onMounted(() => {
  goalname.value = props.goal.name
  goaldescription.value = props.goal.description
})

// Watch changes on goalname
watch(goalname, (newGoalName) => {
    store.state.learninggoal[0].name = newGoalName;
    emit('change-GoalName', newGoalName);
});

// Watch changes on goaldescription
watch(goaldescription, (newGoalDescription) => {
    store.state.learninggoal[0].description = newGoalDescription;
    emit('change-GoalDescription', newGoalDescription);
});
</script>

<template>
  <div>
    <h4 class="font-weight-bold">
      {{ store.state.strings.fromlearningtitel }}
    </h4>
    <div>
      <input
        id="goalnameplaceholder"
        v-model="goalname"
        v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
        class="form-control fancy-input"
        :placeholder="store.state.strings.goalnameplaceholder"
        type="text"
        autofocus
        :disabled="store.state.view=='teacher'"
      >
    </div>
    <div class="mb-4">
      <h4 class="font-weight-bold">
        {{ store.state.strings.fromlearningdescription }}
      </h4>
      <div>
        <textarea
          id="goalsubjectplaceholder"
          v-model="goaldescription"
          v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
          class="form-control fancy-input"
          :placeholder="store.state.strings.goalsubjectplaceholder"
          :disabled="store.state.view=='teacher'"
        />
      </div>
    </div>
  </div>
</template>