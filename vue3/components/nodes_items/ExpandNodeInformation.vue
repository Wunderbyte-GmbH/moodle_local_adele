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
                <div v-if="courses[0] && courses[0].description">
                  <div v-html="courses[0].description"/>
                </div>
                <div v-else-if="courses[0] && courses[0].summary">
                  <div v-html="courses[0].summary"/>
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

.unfold-enter-from, .unfold-leave-to {
  transform: scaleX(0);
  opacity: 0;
  transform-origin: right;
}

.unfold-enter-to, .unfold-leave-from {
  transform: scaleX(1);
  opacity: 1;
  transform-origin: right;
}

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
  display: inline-flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
}

.information {
  display: inline-block;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  border: 1px solid rgba(0,0,0,0.2);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.38);
}

.information:hover {
  background-color: #ababab !important;
  box-shadow: 0 6px 8px rgba(0,0,0,0.2);
}

.fa-info {
  font-size: 30px;
  color: white;
  margin-top: 7px;
}

.additional-card {
  width: 500px;
  padding: 10px;
  text-align: center;
  border-radius: 8px;
  position: absolute;
  left: -472px;
  top: 50px;
  text-align: start;
  z-index: 2;
}

</style>
