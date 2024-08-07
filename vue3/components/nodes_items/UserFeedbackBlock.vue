<script setup>
  import { useStore } from 'vuex';

  // Load Store
  const store = useStore();

  const props = defineProps({
    data: {
      type: Object,
      default: () => ({}),
    },
    title: {
      type: String,
      required: true,
    },
  });

</script>

<template>
  <div v-if="data && data.length > 0 && data[0] !== ''">
    <div
      v-if="title == 'completion_higher'"
      class="feedback-title"
    >
      {{ store.state.strings['nodes_feedback_' + title] }}
    </div>
    <ul class="feedback-list">
      <li
        v-for="(feedback, index) in data"
        :key="index + '_' + title"
        class="feedback-item"
      >
        {{ feedback }}
        <span v-if="index < data.length - 1" class="or-text">
          {{ store.state.strings.course_condition_concatination_or }}
        </span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.feedback-list {
  list-style-type: disc;
  padding-left: 20px;
}

.feedback-item {
  position: relative;
}

.feedback-text {
  text-align: left;
  display: inline-block;
  width: 100%;
}

.or-text {
  display: block;
  font-style: italic;
  text-align: center;
  margin-top: 5px;
  margin-bottom: 5px;
}

.feedback-title {
  font-weight: bold;
  padding: 10px 0;
}

.card-container {
  cursor: pointer;
  justify-content: center;
  align-items: center;
  padding: 5px;
  border-radius: 8px;
  background-color: #EAEAEA;
  text-align: center;
}

.fa-comment {
  color: #333;
  font-size: 20px;
}

.card-container:hover {
  background-color: rgb(213, 207, 207);
}

.feedback-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

textarea {
  width: 100%;
  padding: 10px;
  border-radius: 5px;
  border: 1px solid #ced4da;
  resize: none;
  font-family: inherit;
  font-size: 1rem;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  transition: border-color 0.2s, box-shadow 0.2s;
}

textarea:focus {
  border-color: #80bdff;
  box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
  outline: none;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.5s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
