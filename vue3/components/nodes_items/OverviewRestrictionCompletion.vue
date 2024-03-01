<script setup>
    // Import needed libraries
    import { onMounted, ref, watch } from 'vue';
    import { useStore } from 'vuex';

    
    // Load Store 
    const store = useStore();
    const props = defineProps({
      node: {
        type: Object,
        required: true,
      }
    });
    // Colors for restriction and completion
    const restrictionColor = ref(store.state.strings.LIGHT_STEEL_BLUE);
    const completionColor = ref(store.state.strings.DARK_ORANGE);

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
        triggerGetConditions()
    // watch values from selected node
    watch(() => store.state.learningpath, async () => {
        triggerGetConditions()
    }, { deep: true } );

});
function triggerGetConditions() {
    if (store.state.learningpath && store.state.learningpath.tree) {
      store.state.learningpath.json.tree.nodes.forEach((node) => {
          if (node.id == props.node.node_id) {
              if (node.completion != undefined) {
                conditions.value.completion = getConditions(node.completion.nodes) 
              }
              if (node.restriction != undefined) {
                conditions.value.restriction = getConditions(node.restriction.nodes) 
              }
          }
      })
    }
}
function getConditions(completion_nodes) {
    let count = 0
    let conditions = []
    completion_nodes.forEach((node_completion) => {
        if (node_completion.type != 'feedback' && !(
          store.state.view=='student' && !node_completion.data.visibility
        )) {
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
  <div>
    <div 
      v-if="conditions.restriction" 
      class="card-container"
      :class="{ 'card-hover': showCard }"
      @click="toggleCards"
    >
      <div 
        class="restriction" 
        :style="{ color: restrictionColor }"
      >
        <i class="fa-solid fa-key" />
        <span class="count">
          {{ conditions.restriction.count }}
        </span>
      </div>
      <div 
        class="completion" 
        :style="{ color: completionColor }"
      >
        <i class="fa-solid fa-check-to-slot" />
        <span class="count">
          {{ conditions.completion.count }}
        </span>
      </div>
      <button v-if="showCard" class="cancel-button" @click.stop="toggleCards">
        <i v-if="showCard" class="fa-solid fa-times cancel-icon" @click.stop="toggleCards" />
      </button>
    </div>

    <!-- Left Card -->
    <div 
      v-if="showCard" 
      class="additional-card left" 
      :style="{ backgroundColor: restrictionColor }"
    >
      <!-- Content for the left card -->
      <i class="fa-solid fa-key" />
      <b>
        Restriction
      </b>
      <div v-if="conditions.restriction.count > 0 ">
        <ul class="list-group mt-3">
          <li 
            v-for="(condition, index) in conditions.restriction.conditions" 
            :key="index"
            class="list-group-item"
          >
            {{ condition }}
          </li>
        </ul>
      </div>
      <div v-else>
        <ul class="list-group mt-3">
          <li class="list-group-item">
            No restrictions are defined
          </li>
        </ul>
      </div>
    </div>

    <!-- Right Card -->
    <div 
      v-if="showCard" 
      class="additional-card right" 
      :style="{ backgroundColor: completionColor }"
    >
      <!-- Content for the left card -->
      <i class="fa-solid fa-key" />
      <b>
        Completion
      </b>
      <div v-if="conditions.completion.count > 0 ">
        <ul class="list-group mt-3">
          <li 
            v-for="(condition, index) in conditions.completion.conditions" 
            :key="index"
            class="list-group-item"
          >
            {{ condition }}
          </li>
        </ul>
      </div>
      <div v-else>
        <ul class="list-group mt-3">
          <li class="list-group-item">
            No restrictions are defined
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<style scoped>
.card-container {
  cursor: pointer;
  width: 100%;
  display: -webkit-box;
  width: 100%;
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  font-weight: bold; /* Make the text bold */
}

.card-container:hover {
  background-color: rgb(213, 207, 207); /* Change background color on hover */
}

.restriction,
.completion {
  display: flex;
  align-items: flex-end; /* Align items at the bottom within each child */
  margin-right: 10px; /* Add margin to separate items within each child */
}

.additional-card {
  width: 300px !important;
  padding: 10px !important;
  text-align: center !important;
  border-radius: 8px;
  margin-top: 10px;
  position: absolute;
  top: 70%;
}

.left {
  right: 105%;
}

.right {
  left: 105%;
}

.cancel-button {
  float: inline-end;
  margin-left: auto; 
  background-color: rgb(109, 107, 107);
  color: white;
  border: none;
  border-radius: 4px;
}
</style>
