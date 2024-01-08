<script setup>
    // Import needed libraries
    import { defineProps, onMounted, ref } from 'vue';
    import { useStore } from 'vuex';

    // Colors for restriction and completion
    const restrictionColor = ref("#539be7");
    const completionColor = ref("#f1b00c");

    // Load Store 
    const store = useStore();
    const props = defineProps({
        node: Object,
    });
    // Calculate searched courses

    // Create a ref for conditions
    const conditions = ref([]);
    const showCard = ref(false);

    onMounted(async () => {
        conditions.value = {
        completion: {
            count: 0,
            conditions: null,
        },
        restriction: {
            count: 0,
            conditions: null,
        },
        };
        store.state.learninggoal[0].json.tree.nodes.forEach((node) => {
            if (node.id == props.node.node_id) {
                if (node.completion != undefined) {
                    conditions.value.completion = getConditions(node.completion.nodes) 
                }
                if (node.restriction != undefined) {
                    conditions.value.restriction = getConditions(node.restriction.nodes) 
                }
            }
        }
    )
});
function getConditions(completion_nodes) {
    let count = 0
    let conditions = []
    completion_nodes.forEach((node_completion) => {
        if (node_completion.type != 'feedback') {
            count ++
            conditions.push(node_completion.data.description)
        }
    })
    return {
        count: count,
        conditions: conditions,
    }
}

const toggleCards = () => {
  showCard.value = !showCard.value;
};

</script>

<template>
    <div v-if="conditions.restriction" class="card-container mt-2">

        <div @click="toggleCards" class="card-container">
            <div class="card">
                <div class="restriction" :style="{ color: restrictionColor }">
                    <i class="fa-solid fa-key"></i>
                    <span class="count" >{{ conditions.restriction.count }}</span>
                </div>
                <div class="completion" :style="{ color: completionColor }">
                    <i class="fa-solid fa-check-to-slot"></i>
                    <span class="count">{{ conditions.completion.count }}</span>
                </div>
            </div>
        </div>

        <!-- Left Card -->
        <div v-if="showCard" class="additional-card left" :style="{ backgroundColor: restrictionColor }">
            <!-- Content for the left card -->
            <i class="fa-solid fa-key"></i>
            <b>
                Restriction
            </b>
            <div v-if="conditions.restriction.count > 0 ">
                <ul class="list-group mt-3">
                    <li class="list-group-item" v-for="(condition, index) in conditions.restriction.conditions" :key="index">
                        {{ condition }}
                    </li>
                </ul>
            </div>
            <div v-else>
                <ul class="list-group mt-3">
                    <li class="list-group-item" >
                        No restrictions are defined
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Card -->
        <div v-if="showCard" class="additional-card right" :style="{ backgroundColor: completionColor }">
            <!-- Content for the left card -->
            <i class="fa-solid fa-key"></i>
            <b>
                Completion
            </b>
            <div v-if="conditions.completion.count > 0 ">
                <ul class="list-group mt-3">
                    <li class="list-group-item" v-for="(condition, index) in conditions.completion.conditions" :key="index">
                        {{ condition }}
                    </li>
                </ul>
            </div>
            <div v-else>
                <ul class="list-group mt-3">
                    <li class="list-group-item" >
                        No restrictions are defined
                    </li>
                </ul>
            </div>
        </div>

    </div>
</template>

<style scoped>
.card-container {
  display: flex;
  flex-direction: column; /* Stack children vertically */
  justify-content: flex-end; /* Align items at the bottom */
  height: 35%; /* Occupy full height of the parent */
  cursor: pointer;
}

.card {
  display: -webkit-box;
  width: 100%;
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  font-weight: bold; /* Make the text bold */
}

.restriction,
.completion {
  display: flex;
  align-items: flex-end; /* Align items at the bottom within each child */
  margin-right: 10px; /* Add margin to separate items within each child */
}

.additional-card {
  width: 300px;
  padding: 10px;
  border-radius: 8px;
  margin-top: 10px;
  position: absolute;
}

.left {
  right: 105%;
  top: 70%;
}

.right {
  left: 105%;
}
</style>
