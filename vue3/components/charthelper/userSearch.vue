<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { debounce } from 'lodash';
import { computed } from 'vue';

const store = useStore();

const searchQuery = ref('');
const foundUsers = ref([]);
const searchWarnings = ref('');
const selectedUsers = ref([]);
const isListVisible = ref(false);

const debouncedSearchUser = debounce(async () => {
  const result = await store.dispatch('getFoundUsers', searchQuery.value);
  foundUsers.value = result.list;
  searchWarnings.value = result.warnings;
  isListVisible.value = true;
}, 400);

watch(searchQuery, () => {
  debouncedSearchUser();
});

const addUser = (user) => {
  store.dispatch('createLpEditUsers',
  {
    lpid: store.state.learningPathID,
    userid: user.id,
  });
  if (!selectedUsers.value.some(selected => selected.id === user.id)) {
    selectedUsers.value.push(user);
  }
  isListVisible.value = false;
};

const removeUser = (userId) => {
  const confirmation = confirm(store.state.strings.editordeleteconfirmation);
  if (
    confirmation &&
    selectedUsers.value.length >= 2
  ) {
    store.dispatch('removeLpEditUsers', {
      lpid: store.state.learningPathID,
      userid: userId,
    });
    selectedUsers.value = selectedUsers.value.filter(user => user.id !== userId);
  }
};

// Hide the list if clicking outside of the input or list
const handleClickOutside = (event) => {
  const input = document.querySelector('.user-search-input');
  const list = document.querySelector('.user-list');
  if (input && list && !input.contains(event.target) && !list.contains(event.target)) {
    isListVisible.value = false;
  }
};

const isLearningPathIDZero = computed(() => store.state.learningPathID === 0);

const inputPlaceholder = computed(() =>
  isLearningPathIDZero.value ?
    store.state.strings.onlysetaftersaved :
    store.state.strings.searchuser
);

onMounted(async () => {
  selectedUsers.value = await store.dispatch('getLpEditUsers', store.state.learningPathID);
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});

</script>

<template>
  <div class="col-6">
    <h4>
      {{ store.state.strings.selectuser }}
    </h4>
    <input
      v-model="searchQuery"
      class="form-control mb-2 user-search-input"
      :placeholder="inputPlaceholder"
      @focus="isListVisible = true"
      :disabled="isLearningPathIDZero"
    >
    <div v-if="isListVisible">
      <div v-if="searchWarnings" class="alert alert-warning">
        {{ searchWarnings }}
      </div>
      <div
        v-else-if="foundUsers.length > 0"
        class="user-list bg-white border rounded"
        style="max-height: 200px; overflow-y: auto;"
      >
        <div
          v-for="user in foundUsers"
          :key="user.id"
          class="user-item p-2"
          @click="addUser(user)"
          style="cursor: pointer;"
        >
          {{ user.firstname }} {{ user.lastname }}
        </div>
      </div>
      <div v-else class="alert alert-warning">
        {{ store.state.strings.nousersfound }}
      </div>
    </div>
    <div v-if="selectedUsers.length" class="d-flex flex-wrap mt-2">
      <div
        v-for="user in selectedUsers"
        :key="user.id"
        class="card card-user mb-2 mr-2"
      >
        <div class="card-body p-2 d-flex align-items-center justify-content-between">
          <span>{{ user.firstname }} {{ user.lastname }}</span>
          <button
            v-if="selectedUsers.length > 1"
            class="btn btn-link text-danger p-0"
            @click="removeUser(user.id)"
            :title="store.state.strings.removeuser"
          >
            &times;
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-search-input {
  width: 100%;
}

.user-list {
  position: absolute;
  z-index: 1000;
  width: 100%;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.user-item:hover {
  background-color: #f1f1f1;
}

.card-user {
  background-color: #f9f9f9;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.card-body {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-user:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
</style>
