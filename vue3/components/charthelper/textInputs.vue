<script setup>
import { ref, watch } from 'vue';
import { useStore } from 'vuex';

const props = defineProps({
    goal: Object,
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
        <h4 class="font-weight-bold">{{ store.state.strings.fromlearningtitel }}</h4>
        <div>
            <input
                v-if="$store.state.learningGoalID == 0"
                class="form-control fancy-input"
                :placeholder="store.state.strings.goalnameplaceholder"
                autofocus
                type="text"
                v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
                v-model="goalname"
            />
            <input
                v-else
                class="form-control fancy-input"
                type="text"
                v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
                v-model="props.goal.name"
            />
            </div>
            <div class="mb-4">
            <h4 class="font-weight-bold">{{ store.state.strings.fromlearningdescription }}</h4>
            <div>
                <textarea
                v-if="$store.state.learningGoalID == 0"
                class="form-control fancy-input"
                :placeholder="store.state.strings.goalsubjectplaceholder"
                v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
                v-model="goaldescription"
                ></textarea>
                <textarea
                v-else
                class="form-control fancy-input"
                v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
                v-model="props.goal.description"
                ></textarea>
            </div>
        </div>
    </div>
</template>