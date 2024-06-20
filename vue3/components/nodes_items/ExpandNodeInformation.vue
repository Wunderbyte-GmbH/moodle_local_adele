<script setup>
  // Import needed libraries
  import { computed, ref } from 'vue';
  import { useStore } from 'vuex';

  defineProps({
    courses: {
      type: Object,
      required: true,
    },
  });
  const store = useStore();

  // Create a ref for conditions
  const showCard = ref(false);

  const toggleCard = () => {
    showCard.value = !showCard.value
  }
  const backgroundColor = computed(() => store.state.strings.LIGHT_GRAY)

</script>

<template>
  <div
    class="icon-container "
    :class="{ 'card-hover': showCard, [courses.id + '_node_info_listener']: true}"
    @click="toggleCard()"
  >
    <div>
      <div
        class="information"
        :style="{ backgroundColor: backgroundColor }"
      >
        <i class="fa fa-info" />
      </div>
      <transition name="unfold">
        <div
          v-if="showCard"
          class="additional-card"
          :style="{ backgroundColor: backgroundColor}"
        >
          <ul class="list-group">
            <li
              class="list-group-item"
            >
              <i class="fa fa-pencil" />
              <b>
                Description
              </b>
              <div class="list-group-text">
                <div v-if="courses[0].description">
                  <div v-html="courses[0].description"/>
                </div>
                <div v-else>
                  {{store.state.strings.nodes_no_description}}
                </div>
              </div>
            </li>
          </ul>
        </div>
      </transition>
    </div>
  </div>
</template>

<style scoped>

/* Starting state for entering */
.unfold-enter-from, .unfold-leave-to {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: right; /* Ensures scaling happens left to right */
}

/* Ending state for entering and starting state for leaving */
.unfold-enter-to, .unfold-leave-from {
  transform: scaleX(1);
  opacity: 1;
  transform-origin: right;
}

/* Active state for entering and leaving */
.unfold-enter-active, .unfold-leave-active {
  transition: transform 0.3s ease-out, opacity 0.3s ease-out;
}

.list-group-text{
  text-align: left;
}

.icon-container {
  position: absolute;
  top: -25px;
  right: -25px;
  display: inline-flex; /* Use flexbox for centering */
  justify-content: center;
  align-items: center;
  cursor: pointer;
  z-index: 101;
}

.information {
  display: inline-block;
  width: 50px; /* Diameter of the round button */
  height: 50px; /* Diameter of the round button */
  border-radius: 50%; /* Makes the div round */
  border: 1px solid rgba(0,0,0,0.2);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.38); /* Adds depth with a shadow */
}

.information:hover {
  background-color: #ababab !important;
  box-shadow: 0 6px 8px rgba(0,0,0,0.2); /* Larger shadow on hover for depth */
}

.fa-info {
  font-size: 30px; /* Make the icon larger */
  color: white; /* Change the color if needed */
  margin-top: 7px;
}

.additional-card {
  width: 500px;
  padding: 10px;
  text-align: center;
  border-radius: 8px;
  position: absolute;
  z-index: 11;
  left: -472px;
  top: 50px;
  text-align: start;
}

</style>
